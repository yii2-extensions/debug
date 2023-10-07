<?php

declare(strict_types=1);

namespace yii\debug\models\timeline;

use yii\data\ArrayDataProvider;
use yii\debug\panels\TimelinePanel;

use function round;
use function sprintf;

/**
 * DataProvider implements a data provider based on a data array.
 */
class DataProvider extends ArrayDataProvider
{
    protected TimelinePanel $panel;

    public function __construct(TimelinePanel $panel, array $config = [])
    {
        parent::__construct($config);

        $this->panel = $panel;
    }

    /**
     * Getting HEX color based on model duration.
     */
    public function getColor(array $model): string
    {
        $width = $model['css']['width'] ?? $this->getWidth($model);

        foreach ($this->panel->getColors() as $percent => $color) {
            if ($width >= $percent) {
                return $color;
            }
        }

        return '#d6e685';
    }

    /**
     * Returns the offset left item, percentage of the total width.
     */
    public function getLeft(array $model): float|int
    {
        return $this->getTime($model) / ($this->panel->getDuration() / 100);
    }

    /**
     * Returns item duration, milliseconds.
     */
    public function getTime(array $model): float
    {
        return $model['timestamp'] - $this->panel->getStart();
    }

    /**
     * Returns item width percent of the total width.
     */
    public function getWidth(array $model): float|int
    {
        return $model['duration'] / ($this->panel->getDuration() / 100);
    }

    /**
     * Returns item, css class.
     */
    public function getCssClass(array $model): string
    {
        $class = 'time';
        $class .= (($model['css']['left'] > 15) && ($model['css']['left'] + $model['css']['width'] > 50))
            ? ' right' : ' left';

        return $class;
    }

    /**
     * ruler items, key milliseconds, value offset left.
     */
    public function getRulers(int $line = 10): array
    {
        if ($line === 0) {
            return [];
        }

        $data = [0];
        $percent = ($this->panel->getDuration() / 100);
        $row = $this->panel->getDuration() / $line;
        $precision = $row > 100 ? -2 : -1;

        for ($i = 1; $i < $line; $i++) {
            $ms = round($i * $row, $precision);
            $data[$ms] = $ms / $percent;
        }

        return $data;
    }

    /**
     * ```php
     * [
     *   0 => string, memory usage (MB)
     *   1 => float, Y position (percent)
     * ]
     */
    public function getMemory(array $model): array|null
    {
        if (empty($model['memory'])) {
            return null;
        }

        return [
            sprintf('%.2f MB', $model['memory'] / 1048576),
            $model['memory'] / ($this->panel->getMemory() / 100),
        ];
    }

    protected function prepareModels(): array
    {
        if (($models = $this->allModels) === null) {
            return [];
        }

        $child = [];

        foreach ($models as $key => &$model) {
            $model['timestamp'] *= 1000;
            $model['duration'] *= 1000;
            $model['child'] = 0;
            $model['css']['width'] = $this->getWidth($model);
            $model['css']['left'] = $this->getLeft($model);
            $model['css']['color'] = $this->getColor($model);
            foreach ($child as $id => $timestamp) {
                if ($timestamp > $model['timestamp']) {
                    ++$models[$id]['child'];
                } else {
                    unset($child[$id]);
                }
            }
            $child[$key] = $model['timestamp'] + $model['duration'];
        }

        return $models;
    }
}
