<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Data\QueryInput;
use PHPForge\Debug\Helper\{EmptyState, Format};
use PHPForge\Debug\Panel\MemorySample;
use PHPForge\Debug\Panel\Profile\{ProfileRow, ProfilingSnapshot};
use PHPForge\Debug\Panel\Timeline\{TimelineGeometry, TimelineMemoryRenderer, TimelineRenderer};
use PHPForge\Debug\Storage\RequestSummary;
use UIAwesome\Html\Flow\P;
use Yii;
use yii\debug\models\search\ProfileSearch;
use yii\debug\Panel;

use function array_values;
use function str_replace;

/**
 * Renders the profiling spans captured by the Profiling collector.
 *
 * Presents the request Timeline and sortable details from one filtered set of profiling spans; data acquisition lives
 * in {@see \yii\debug\collectors\ProfilingCollector}.
 */
class ProfilingPanel extends Panel implements ProvidesMemorySamples, RequestSummaryAwarePanelInterface
{
    protected const string ICON = 'profiling';
    protected const string NAME = 'Profiling';

    private ProfilingSnapshot|null $snapshot = null;
    private RequestSummary|null $summary = null;

    /**
     * Renders the unified Timeline and profiling-details view.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new ProfileSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        $filteredRows = $this->filteredRows($dataProvider->allModels);

        $processingTime = number_format(($this->getProcessingTime() ?? 0.0) * 1000);

        return Yii::$app->view->render(
            'panels/profile/detail',
            [
                'dataProvider' => $dataProvider,
                'filterAction' => $this->getUrl(),
                'filterHiddenParams' => $this->filterHiddenParams(),
                'memory' => Format::bytesToMb($this->getMemoryUsage(), 2),
                'panel' => $this,
                'searchModel' => $searchModel,
                'time' => "{$processingTime} ms",
                'timeline' => $this->renderTimeline($filteredRows),
            ],
            $this,
        );
    }

    /**
     * @return list<MemorySample> Memory readings recorded alongside each captured profile message.
     */
    public function getMemorySamples(): array
    {
        return $this->snapshot?->samples() ?? [];
    }

    public function getMemoryUsage(): int
    {
        return $this->snapshot === null ? 0 : $this->snapshot->memory;
    }

    /**
     * Returns the typed profiling spans consumed by the unified Timeline and details grid.
     *
     * @return list<ProfileRow> Spans in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
     */
    public function getModels(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    public function getProcessingTime(): float|null
    {
        return $this->snapshot?->time;
    }

    /**
     * Hides the "Profiling" title from the toolbar; the gauge icon plus the time/memory metrics are self-explanatory.
     *
     * @return array<string, mixed> Toolbar payload with the title blanked on success.
     */
    #[Override]
    public function getToolbarData(): array
    {
        $data = parent::getToolbarData();

        if ($data !== [] && !$this->hasError()) {
            $data['title'] = '';
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = ProfilingSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Supplies the canonical request start time used to position captured spans on the Timeline.
     */
    public function setRequestSummary(RequestSummary $summary): void
    {
        $this->summary = $summary;
    }

    /**
     * Builds the toolbar items: the total processing time and the peak memory usage, rendered as neutral metric
     * readouts.
     *
     * @return array<int, array<string, mixed>> Toolbar items in display order.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $processingTime = number_format(($this->getProcessingTime() ?? 0.0) * 1000);

        return [
            [
                'title' => 'Total processing time',
                'value' => "{$processingTime} ms",
            ],
            [
                'title' => 'Peak memory',
                'value' => Format::bytesToMb($this->getMemoryUsage(), 3),
            ],
        ];
    }

    /**
     * Narrows the filtered data-provider payload to typed rows while retaining capture order.
     *
     * @param array<array-key, mixed> $models
     *
     * @return list<ProfileRow>
     */
    private function filteredRows(array $models): array
    {
        $rows = [];

        foreach (array_values($models) as $model) {
            if ($model instanceof ProfileRow) {
                $rows[] = $model;
            }
        }

        return $rows;
    }

    /**
     * Preserves adapter-owned routing and display state when the shared filter form is submitted.
     *
     * @return array<string, string>
     */
    private function filterHiddenParams(): array
    {
        $params = [
            'r' => ($this->module?->getUniqueId() ?? 'debug') . '/view',
            'panel' => 'profiling',
            'tag' => $this->tag,
        ];

        $queryParams = Yii::$app->getRequest()->getQueryParams();

        foreach (['sort', 'per-page', 'yii_debug_theme'] as $name) {
            $value = QueryInput::scalar($queryParams, $name);

            if ($value !== null && $value !== '') {
                $params[$name] = $value;
            }
        }

        return $params;
    }

    /**
     * @param list<ProfileRow> $rows Filtered spans in capture order.
     */
    private function renderTimeline(array $rows): string
    {
        $snapshot = $this->snapshot;
        $summary = $this->summary;

        if (
            $snapshot === null
            || $summary === null
            || $summary->time <= 0.0
            || $snapshot->time <= 0.0
            || $snapshot->memory <= 0
        ) {
            return $this->renderTimelineUnavailable();
        }

        $start = $summary->time * 1000;
        $duration = $snapshot->time * 1000;

        $spans = TimelineGeometry::spans($rows, $start, $duration);

        if ($spans === []) {
            return EmptyState::card(
                'Timeline unavailable',
                P::tag()
                    ->content('The filtered spans cannot be positioned on this request timeline.'),
                P::tag()->content('The profiling details remain available below.'),
            );
        }

        $memorySvg = TimelineMemoryRenderer::render(
            $this->timelineMemorySamples(),
            $start,
            $duration,
            $snapshot->memory,
        );

        return str_replace(
            '<wbr>',
            '',
            TimelineRenderer::renderChart(
                $spans,
                TimelineGeometry::rulers($duration, 4),
                $memorySvg,
                $snapshot->memory,
            ),
        );
    }

    private function renderTimelineUnavailable(): string
    {
        return EmptyState::card(
            'Timeline unavailable',
            P::tag()
                ->content(
                    'This capture does not contain the valid request start, duration, and peak-memory values required '
                    . 'to position the chart.',
                ),
            P::tag()->content('The profiling details remain available below.'),
        );
    }

    /**
     * @return list<MemorySample> Profiling and log samples used by the Timeline memory graph.
     */
    private function timelineMemorySamples(): array
    {
        $samples = $this->getMemorySamples();
        $logPanel = $this->module?->panels['log'] ?? null;

        if ($logPanel instanceof ProvidesMemorySamples) {
            $samples = [
                ...$samples,
                ...$logPanel->getMemorySamples(),
            ];
        }

        return $samples;
    }
}
