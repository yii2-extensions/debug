<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

/**
 * Typed view-model for a single profile block consumed by the profile grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\ProfilingPanel} (already typed) but exposes it through a
 * concrete class so GridView callbacks receive a fully narrowed value instead of the loose `mixed` argument PHPStan
 * widens to at the data-provider boundary.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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
