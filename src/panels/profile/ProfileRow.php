<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

/**
 * Typed view-model for a single profile block consumed by the profile grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\ProfilingPanel} after every value has been narrowed, so
 * GridView callbacks read typed properties without further `mixed` narrowing at the data-provider boundary.
 */
final readonly class ProfileRow
{
    public function __construct(
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $timestamp,
        /**
         * Block execution time in milliseconds.
         */
        public float $duration,
        /**
         * Profile category attached to the `Yii::beginProfile()` token.
         */
        public string $category,
        /**
         * Profile token / informational label captured at `Yii::beginProfile()`.
         */
        public string $info,
        /**
         * Nesting level of the block, used to render an indentation arrow per level.
         */
        public int $level,
        /**
         * Zero-based sequence index assigned by the panel.
         */
        public int $seq,
    ) {}
}
