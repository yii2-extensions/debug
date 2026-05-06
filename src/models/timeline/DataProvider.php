<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\timeline;

use RuntimeException;
use yii\data\ArrayDataProvider;
use yii\debug\panels\TimelinePanel;

use function is_array;
use function is_numeric;
use function sprintf;

/**
 * DataProvider implements a data provider based on a data array.
 */
class DataProvider extends ArrayDataProvider
{
    protected TimelinePanel|null $panel = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(TimelinePanel $panel, array $config = [])
    {
        $this->panel = $panel;

        parent::__construct($config);
    }

    /**
     * Returns the HEX color associated with the model duration bucket.
     *
     * @param array<array-key, mixed> $model
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
     * Returns the CSS class describing the item's left/right alignment within its row.
     *
     * @param array<array-key, mixed> $model
     */
    public function getCssClass(array $model): string
    {
        $left = self::cssNumber($model, 'left') ?? 0.0;
        $width = self::cssNumber($model, 'width') ?? 0.0;

        return 'time' . (($left > 15) && ($left + $width > 50) ? ' right' : ' left');
    }

    /**
     * Returns the offset left of the item, expressed as a percentage of the total width.
     *
     * @param array<array-key, mixed> $model
     */
    public function getLeft(array $model): float
    {
        return $this->getTime($model) / ($this->panel()->getDuration() / 100);
    }

    /**
     * Returns memory usage as `[formatted_mb, y_position_percent]`, or `null` when no memory entry exists.
     *
     * @param array<array-key, mixed> $model
     *
     * @return array{0: string, 1: float}|null
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
            sprintf('%.2f MB', $memoryFloat / 1048576),
            $memoryFloat / ($this->panel()->getMemory() / 100),
        ];
    }

    /**
     * Returns ruler tick positions keyed by milliseconds with their offset-left percentage as the value.
     *
     * @return array<int, float>
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
     * Returns the item's elapsed time relative to the request start, in milliseconds.
     *
     * @param array<array-key, mixed> $model
     */
    public function getTime(array $model): float
    {
        $timestamp = $model['timestamp'] ?? 0;

        return (is_numeric($timestamp) ? (float) $timestamp : 0.0) - $this->panel()->getStart();
    }

    /**
     * Returns the item's width as a percentage of the total request duration.
     *
     * @param array<array-key, mixed> $model
     */
    public function getWidth(array $model): float
    {
        $duration = $model['duration'] ?? 0;

        return (is_numeric($duration) ? (float) $duration : 0.0) / ($this->panel()->getDuration() / 100);
    }

    /**
     * @return list<array<array-key, mixed>>
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
     * @param array<array-key, mixed> $model
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
     * Returns the bound {@see TimelinePanel}, asserting that it has been set by the constructor.
     */
    private function panel(): TimelinePanel
    {
        if ($this->panel === null) {
            throw new RuntimeException('TimelinePanel has not been set on the data provider.');
        }

        return $this->panel;
    }
}
