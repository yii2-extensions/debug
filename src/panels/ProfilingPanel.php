<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\debug\helpers\{Coerce, Format};
use yii\debug\models\search\ProfileSearch;
use yii\debug\Panel;
use yii\helpers\Url;
use yii\log\Logger;

use function count;
use function is_array;
use function is_int;

/**
 * Captures profile-level log messages emitted by `Yii::beginProfile()` and renders the per-block timings in the
 * Profiling panel.
 *
 * Records the request peak memory and total processing time alongside the profile messages, so the detail view can
 * surface the totals next to the sortable per-block grid and link to the Timeline panel.
 *
 * @extends Panel<array{memory?: mixed, time?: mixed, messages?: mixed}>
 */
class ProfilingPanel extends Panel
{
    /**
     * @var array<int, array{
     *   duration: float,
     *   category: string,
     *   info: string,
     *   level: int,
     *   timestamp: float,
     *   seq: int
     * }>|null Cached typed profile rows consumed by the profile grid.
     */
    private array|null $models = null;

    /**
     * Renders the detail view with the profile grid, total time, peak memory, and the Timeline panel cross-link.
     */
    public function getDetail(): string
    {
        $profileData = $this->getProfileData();

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
                'memory' => Format::bytesToMb($profileData['memory'], 3),
                'panel' => $this,
                'searchModel' => $searchModel,
                'time' => number_format($profileData['time'] * 1000) . ' ms',
                'timelineUrl' => $timelineUrl,
            ],
            $this,
        );
    }

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Profiling';
    }

    /**
     * Renders the toolbar summary chip with the total processing time and peak memory.
     */
    public function getSummary(): string
    {
        $profileData = $this->getProfileData();

        return Yii::$app->view->render(
            'panels/profile/summary',
            [
                'memory' => Format::bytesToMb($profileData['memory'], 3),
                'panel' => $this,
                'time' => number_format($profileData['time'] * 1000) . ' ms',
            ],
            $this,
        );
    }

    /**
     * Hides the "Profiling" title from the toolbar; the gauge icon plus the time/memory metrics are self-explanatory.
     *
     * @return array<string, mixed> Toolbar payload with the title blanked on success.
     */
    public function getToolbarData(): array
    {
        $data = parent::getToolbarData();

        if ($data !== [] && !$this->hasError()) {
            $data['title'] = '';
        }

        return $data;
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'profiling';
    }

    /**
     * Snapshots the captured profile messages, the peak memory usage, and the total request time.
     *
     * @return array{memory: int, time: float, messages: array<int, array<int|string, mixed>>} Captured payload, with
     * `time` in seconds and `memory` in bytes.
     */
    public function save(): array
    {
        $messages = $this->getLogMessages(Logger::LEVEL_PROFILE);

        $requestStart = Coerce::floatOrNull($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? microtime(true);

        return [
            'memory' => memory_get_peak_usage(),
            'time' => microtime(true) - $requestStart,
            'messages' => $messages,
        ];
    }

    /**
     * Builds and caches the typed profile rows consumed by the profile grid.
     *
     * Suitable for {@see \yii\data\ArrayDataProvider}.
     *
     * @return array<int, array{
     *   duration: float,
     *   category: string,
     *   info: string,
     *   level: int,
     *   timestamp: float,
     *   seq: int
     * }> Profile rows in capture order, with `duration` and `timestamp` in milliseconds.
     */
    protected function getModels(): array
    {
        if ($this->models === null) {
            $this->models = [];

            $timings = Yii::getLogger()->calculateTimings($this->getProfileData()['messages']);

            foreach ($timings as $seq => $profileTiming) {
                $model = self::normalizeTiming($profileTiming, is_int($seq) ? $seq : count($this->models));

                if ($model !== null) {
                    $this->models[] = $model;
                }
            }
        }

        return $this->models;
    }

    /**
     * Builds the toolbar items: the total processing time and the peak memory usage.
     *
     * @return array<int, array<string, mixed>> Toolbar items in display order.
     */
    protected function getToolbarItems(): array
    {
        $profileData = $this->getProfileData();

        return [
            [
                'status' => 'info',
                'title' => 'Total processing time',
                'value' => number_format($profileData['time'] * 1000) . ' ms',
            ],
            [
                'status' => 'info',
                'title' => 'Peak memory',
                'value' => Format::bytesToMb($profileData['memory'], 3),
            ],
        ];
    }

    /**
     * Narrows the saved panel data into the typed `memory` / `time` / `messages` shape consumed by the renderers.
     *
     * @return array{memory: int, time: float, messages: array<int, array<int|string, mixed>>} Normalized payload with
     * defensible defaults (`0` / `0.0` / `[]`) for missing fields.
     */
    private function getProfileData(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'memory' => Coerce::intOrNull($data['memory'] ?? null) ?? 0,
            'time' => Coerce::floatOrNull($data['time'] ?? null) ?? 0.0,
            'messages' => self::normalizeMessages($data['messages'] ?? []),
        ];
    }

    /**
     * Filters the raw saved messages to keep only array entries.
     *
     * @param array<int|string, mixed>|mixed $messages Raw saved messages.
     *
     * @return array<int, array<int|string, mixed>> Reindexed list of message arrays.
     */
    private static function normalizeMessages(mixed $messages): array
    {
        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $normalized[] = $message;
            }
        }

        return $normalized;
    }

    /**
     * Narrows a raw timing returned by the Yii logger into the typed profile-row shape, returning `null` when either
     * `duration` or `timestamp` is missing or non-numeric.
     *
     * @param mixed $timing Raw timing returned by Yii logger.
     * @param int $seq Sequence index to assign to the resulting row.
     *
     * @return array{
     *   duration: float,
     *   category: string,
     *   info: string,
     *   level: int,
     *   timestamp: float,
     *   seq: int
     * }|null Typed profile row with `duration` and `timestamp` in milliseconds, or `null` when the input was
     * incomplete.
     */
    private static function normalizeTiming(mixed $timing, int $seq): array|null
    {
        if (!is_array($timing)) {
            return null;
        }

        $duration = Coerce::floatOrNull($timing['duration'] ?? null);
        $timestamp = Coerce::floatOrNull($timing['timestamp'] ?? null);

        if ($duration === null || $timestamp === null) {
            return null;
        }

        return [
            'duration' => $duration * 1000,
            'category' => Coerce::stringOrNull($timing['category'] ?? null) ?? '',
            'info' => Coerce::stringOrNull($timing['info'] ?? null) ?? '',
            'level' => Coerce::intOrNull($timing['level'] ?? null) ?? 0,
            'timestamp' => $timestamp * 1000,
            'seq' => $seq,
        ];
    }
}
