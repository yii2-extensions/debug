<?php

declare(strict_types=1);

namespace yii\debug\panels;

/**
 * One point of the timeline memory chart: when the sample was taken and how much memory was in use.
 */
final readonly class MemorySample
{
    public function __construct(
        /**
         * Sample timestamp in milliseconds since the Unix epoch.
         */
        public float $time,
        /**
         * Memory usage in bytes at that timestamp.
         */
        public int $memory,
    ) {}
}
