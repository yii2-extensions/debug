<?php

declare(strict_types=1);

namespace yii\debug\models\timeline;

use Override;
use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPForge\Debug\Panel\Timeline\{TimelineGeometry, TimelineSpanRow};
use yii\data\ArrayDataProvider;
use yii\debug\panels\TimelinePanel;

/**
 * Wraps the timeline records as a sortable provider that derives per-row CSS layout fields for the timeline view.
 *
 * Computes each row's left offset, width, and child-count overlap relative to the bound {@see TimelinePanel} so the
 * view can render every bar without recomputing geometry on every callback.
 */
class DataProvider extends ArrayDataProvider
{
    /**
     * @param TimelinePanel $panel Panel providing the request start time, total duration, and peak memory.
     * @param array<string, mixed> $config Standard {@see ArrayDataProvider} configuration.
     */
    public function __construct(protected TimelinePanel $panel, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Returns the row's left offset as a percentage of the total request duration.
     */
    public function getLeft(ProfileRow $row): float
    {
        return $this->getTime($row) / ($this->panel->getDuration() / 100);
    }

    /**
     * Returns adaptive ruler tick positions keyed by milliseconds, valued by their left-offset percentage.
     *
     * Ticks land on "nice" steps ({1, 2, 5} x 10^n, minimum 1 ms) derived from the request duration, so labels stay
     * round and never collide; the last tick is dropped when it falls within a quarter step of the right edge.
     *
     * @param int $line Maximum number of ticks. Values below `1` disable the ruler entirely, as does a request
     * without a measurable duration.
     *
     * @return array<int, float> Tick positions keyed by absolute milliseconds, valued by left-offset percentage.
     */
    public function getRulers(int $line = 6): array
    {
        return TimelineGeometry::rulers($this->panel->getDuration(), $line);
    }

    /**
     * Returns the row's elapsed time relative to the request start, in milliseconds.
     */
    public function getTime(ProfileRow $row): float
    {
        return $row->timestamp - $this->panel->getStart();
    }

    /**
     * Returns the row's width as a percentage of the total request duration.
     */
    public function getWidth(ProfileRow $row): float
    {
        return $row->duration / ($this->panel->getDuration() / 100);
    }

    /**
     * Derives the per-row CSS layout, then narrows every block into a typed span row.
     *
     * @return list<TimelineSpanRow> Prepared span rows ready for the view.
     */
    #[Override]
    protected function prepareModels(): array
    {
        $spans = [];

        foreach ($this->allModels as $model) {
            if ($model instanceof ProfileRow) {
                $spans[] = TimelineSpanRow::from($model, $this->getLeft($model), $this->getWidth($model));
            }
        }

        return $spans;
    }
}
