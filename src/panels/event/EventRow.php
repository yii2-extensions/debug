<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

/**
 * Typed view-model for a single event row consumed by the events grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\EventPanel::save()} (already typed) but exposes it through a
 * concrete class so GridView callbacks receive a fully narrowed value instead of the loose `mixed` argument PHPStan
 * widens to at the data-provider boundary.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class EventRow
{
    public function __construct(
        /**
         * Capture timestamp in seconds since the Unix epoch (`0.0` when the original payload had no time).
         */
        public float $time,
        /**
         * Event name (for example. `EVENT_AFTER_REQUEST`).
         */
        public string $name,
        /**
         * Fully qualified class name of the event object.
         */
        public string $class,
        /**
         * `'1'` when the event was triggered statically (no sender), `'0'` otherwise. Stored as string to round-trip
         * through the search model's `boolean` rule.
         */
        public string $isStatic,
        /**
         * Fully qualified class name of the sender, or empty string when the event was triggered statically.
         */
        public string $senderClass,
    ) {}
}
