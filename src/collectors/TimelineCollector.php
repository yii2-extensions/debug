<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;

use function memory_get_peak_usage;
use function microtime;

/**
 * Captures the request boundaries for the Timeline panel.
 *
 * Snapshots the request start, end, and peak memory so the timeline chart can place the profile spans on a shared
 * time axis.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\TimelineCollector())->capture();
 * ```
 */
class TimelineCollector extends Collector
{
    /**
     * Snapshots the request start (`$_SERVER['REQUEST_TIME_FLOAT']` with `microtime(true)` fallback), end, and peak
     * memory.
     *
     * @return TimelineSnapshot|null Captured timeline payload; `null` when the collector never started.
     */
    public function capture(): TimelineSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        return new TimelineSnapshot(
            start: Coerce::floatOrNull($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? microtime(true),
            end: microtime(true),
            memory: memory_get_peak_usage(),
        );
    }

    /**
     * Returns the stable ID pairing this collector with the Timeline panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\TimelineCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'timeline';
    }
}
