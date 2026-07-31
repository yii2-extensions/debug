<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

/**
 * Typed capture-wide maxima for the History grid's micro-gauges.
 *
 * Computed once per page from the visible data-provider models, so every Duration/Memory cell scales its rail
 * against the same reference.
 */
final readonly class HistoryScale
{
    public function __construct(
        /**
         * Largest captured processing time on the page, in seconds; `0.0` when no row carries one.
         */
        public float $maxProcessingTime,
        /**
         * Largest captured peak memory on the page, in bytes; `0` when no row carries one.
         */
        public int $maxPeakMemory,
    ) {}

    /**
     * Scans the visible models (ignoring rows without a captured value) and returns the page maxima.
     *
     * @param array<array-key, mixed> $models Rows as supplied by the data provider.
     */
    public static function fromModels(array $models): self
    {
        $maxProcessingTime = 0.0;
        $maxPeakMemory = 0;

        foreach ($models as $model) {
            $row = HistoryRow::fromMixed($model);

            if ($row->processingTime !== null) {
                $maxProcessingTime = max($maxProcessingTime, $row->processingTime);
            }

            if ($row->peakMemory !== null) {
                $maxPeakMemory = max($maxPeakMemory, $row->peakMemory);
            }
        }

        return new self(
            maxProcessingTime: $maxProcessingTime,
            maxPeakMemory: $maxPeakMemory,
        );
    }
}
