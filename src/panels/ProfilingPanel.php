<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use Yii;
use yii\debug\helpers\{Coerce, Format};
use yii\debug\models\search\ProfileSearch;
use yii\debug\Panel;
use yii\debug\panels\profile\{ProfileRow, ProfilingSnapshot};
use yii\helpers\Url;
use yii\log\Logger;

use function memory_get_peak_usage;
use function microtime;

/**
 * Captures profile-level log messages emitted by `Yii::beginProfile()` and renders the per-block timings in the
 * Profiling panel.
 *
 * Records the request peak memory and total processing time alongside the profile messages, so the detail view can
 * surface the totals next to the sortable per-block grid and link to the Timeline panel.
 */
class ProfilingPanel extends Panel implements ProvidesMemorySamples
{
    protected const string ICON = 'profiling';
    protected const string NAME = 'Profiling';

    private ProfilingSnapshot|null $snapshot = null;

    /**
     * Snapshots the captured profile messages, the peak memory usage, and the total request time.
     */
    public function capture(): ProfilingSnapshot
    {
        $messages = $this->getLogMessages(Logger::LEVEL_PROFILE);

        $requestStart = Coerce::floatOrNull($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? microtime(true);

        $this->snapshot = ProfilingSnapshot::capture(
            memory_get_peak_usage(),
            microtime(true) - $requestStart,
            $messages,
        );

        return $this->snapshot;
    }

    /**
     * Renders the detail view with the profile grid, total time, peak memory, and the Timeline panel cross-link.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new ProfileSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        $module = $this->module;

        $timelineUrl = $module === null
            ? '#'
            : Url::to(
                [
                    '/' . $module->getUniqueId() . '/default/view',
                    'panel' => 'timeline',
                    'tag' => $this->tag,
                ],
            );

        return Yii::$app->view->render(
            'panels/profile/detail',
            [
                'dataProvider' => $dataProvider,
                'memory' => Format::bytesToMb($this->getMemoryUsage(), 3),
                'panel' => $this,
                'searchModel' => $searchModel,
                'time' => number_format(($this->getProcessingTime() ?? 0.0) * 1000) . ' ms',
                'timelineUrl' => $timelineUrl,
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
     * Returns the typed profile blocks consumed by the profile grid and the Timeline panel.
     *
     * @return list<ProfileRow> Blocks in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
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
     * Builds the toolbar items: the total processing time and the peak memory usage, rendered as neutral metric
     * readouts.
     *
     * @return array<int, array<string, mixed>> Toolbar items in display order.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        return [
            [
                'title' => 'Total processing time',
                'value' => number_format(($this->getProcessingTime() ?? 0.0) * 1000) . ' ms',
            ],
            [
                'title' => 'Peak memory',
                'value' => Format::bytesToMb($this->getMemoryUsage(), 3),
            ],
        ];
    }
}
