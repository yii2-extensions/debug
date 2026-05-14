<?php

declare(strict_types=1);

namespace yii\debug\models\timeline;

use RuntimeException;
use yii\data\ArrayDataProvider;
use yii\debug\helpers\Format;
use yii\debug\panels\TimelinePanel;

use function is_array;
use function is_numeric;

/**
 * Wraps the timeline records as a sortable provider that derives per-row CSS layout fields for the timeline view.
 *
 * Computes each row's left offset, width, color band, and child-count overlap relative to the bound
 * {@see TimelinePanel} so the view can render every bar without recomputing geometry on every callback.
 */
class DataProvider extends ArrayDataProvider
{
    protected TimelinePanel|null $panel = null;

    /**
     * @param TimelinePanel $panel Panel providing the request start time, total duration, and color buckets.
     * @param array<string, mixed> $config Standard {@see ArrayDataProvider} configuration.
     */
    public function __construct(TimelinePanel $panel, array $config = [])
    {
        $this->panel = $panel;

        parent::__construct($config);
    }

    /**
     * Returns the HEX color associated with the model's duration bucket.
     *
     * @param array<array-key, mixed> $model Timeline row carrying a `css.width` percentage.
     */
    public function getColor(array $model): string
    {
        $width = self::cssNumber($model, 'width') ?? $this->getWidth($model);

        foreach ($this->panel()->getColors() as $percent => $color) {
            if ($width >= (float) $percent) {
                return $color;
            }
        }

        return '#d6e685';
    }

    /**
     * Returns the CSS class describing the row's left/right alignment within the timeline.
     *
     * @param array<array-key, mixed> $model Timeline row carrying `css.left` and `css.width` percentages.
     */
    public function getCssClass(array $model): string
    {
        $left = self::cssNumber($model, 'left') ?? 0.0;
        $width = self::cssNumber($model, 'width') ?? 0.0;

        return 'time' . (($left > 15) && ($left + $width > 50) ? ' right' : ' left');
    }

    /**
     * Returns the row's left offset as a percentage of the total request duration.
     *
     * @param array<array-key, mixed> $model Timeline row carrying a `timestamp` entry.
     */
    public function getLeft(array $model): float
    {
        return $this->getTime($model) / ($this->panel()->getDuration() / 100);
    }

    /**
     * Returns the memory usage as a `[formatted_mb, y_position_percent]` pair, or `null` when no memory entry exists.
     *
     * @param array<array-key, mixed> $model Timeline row that may carry a numeric `memory` entry.
     *
     * @return array{0: string, 1: float}|null Formatted memory string and its Y position, or `null` when unavailable.
     */
    public function getMemory(array $model): array|null
    {
        $memory = $model['memory'] ?? null;

        if (!is_numeric($memory)) {
            return null;
        }

        $memoryFloat = (float) $memory;

        if ($memoryFloat <= 0.0) {
            return null;
        }

        return [
            Format::bytesToMb($memoryFloat),
            $memoryFloat / ($this->panel()->getMemory() / 100),
        ];
    }

    /**
     * Returns ruler tick positions keyed by milliseconds, valued by their left-offset percentage.
     *
     * @param int $line Number of ruler segments. `0` disables the ruler entirely.
     *
     * @return array<int, float> Tick positions keyed by absolute milliseconds, valued by left-offset percentage.
     */
    public function getRulers(int $line = 10): array
    {
        if ($line === 0) {
            return [];
        }

        $duration = $this->panel()->getDuration();

        $percent = $duration / 100;
        $row = $duration / $line;
        $precision = $row > 100 ? -2 : -1;
        $data = [0 => 0.0];

        for ($i = 1; $i < $line; $i++) {
            $ms = (int) round($i * $row, $precision);

            $data[$ms] = $ms / $percent;
        }

        return $data;
    }

    /**
     * Returns the row's elapsed time relative to the request start, in milliseconds.
     *
     * @param array<array-key, mixed> $model Timeline row carrying a numeric `timestamp` entry.
     */
    public function getTime(array $model): float
    {
        $timestamp = $model['timestamp'] ?? 0;

        return (is_numeric($timestamp) ? (float) $timestamp : 0.0) - $this->panel()->getStart();
    }

    /**
     * Returns the row's width as a percentage of the total request duration.
     *
     * @param array<array-key, mixed> $model Timeline row carrying a numeric `duration` entry.
     */
    public function getWidth(array $model): float
    {
        $duration = $model['duration'] ?? 0;

        return (is_numeric($duration) ? (float) $duration : 0.0) / ($this->panel()->getDuration() / 100);
    }

    /**
     * Normalizes the raw input rows, converts seconds to milliseconds, derives the per-row CSS layout, and tracks
     * nested child counts so the view can shade overlapping spans.
     *
     * @return list<array<array-key, mixed>> Prepared rows ready for the data provider.
     */
    protected function prepareModels(): array
    {
        $rawModels = $this->allModels;

        if ($rawModels === []) {
            return [];
        }

        /** @var array<int|string, array<string, mixed>> $models */
        $models = [];

        foreach ($rawModels as $key => $rawModel) {
            if (is_array($rawModel)) {
                $models[$key] = $rawModel;
            }
        }

        $child = [];

        foreach ($models as $key => &$model) {
            $rawTimestamp = $model['timestamp'] ?? 0;
            $rawDuration = $model['duration'] ?? 0;

            $timestamp = (is_numeric($rawTimestamp) ? (float) $rawTimestamp : 0.0) * 1000;
            $duration = (is_numeric($rawDuration) ? (float) $rawDuration : 0.0) * 1000;

            $model['timestamp'] = $timestamp;
            $model['duration'] = $duration;
            $model['child'] = 0;
            $model['css'] = [
                'width' => $this->getWidth($model),
                'left' => $this->getLeft($model),
                'color' => $this->getColor($model),
            ];

            foreach ($child as $id => $closesAt) {
                if ($closesAt > $timestamp) {
                    $existing = $models[$id]['child'] ?? 0;

                    $models[$id]['child'] = (is_numeric($existing) ? (int) $existing : 0) + 1;
                } else {
                    unset($child[$id]);
                }
            }

            $child[$key] = $timestamp + $duration;
        }

        unset($model);

        return array_values($models);
    }

    /**
     * Returns a numeric value from `$model['css'][$key]`, or `null` when the entry is missing or non-numeric.
     *
     * @param array<array-key, mixed> $model Timeline row whose `css` sub-array is read.
     * @param string $key Key inside `css` to coerce to a float.
     */
    private static function cssNumber(array $model, string $key): float|null
    {
        $css = $model['css'] ?? null;

        if (!is_array($css)) {
            return null;
        }

        $value = $css[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns the bound {@see TimelinePanel}, asserting that the constructor wired it.
     *
     * @throws RuntimeException When the panel was somehow cleared after construction.
     */
    private function panel(): TimelinePanel
    {
        if ($this->panel === null) {
            throw new RuntimeException('TimelinePanel has not been set on the data provider.');
        }

        return $this->panel;
    }
}
