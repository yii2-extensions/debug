<?php

declare(strict_types=1);

namespace yii\debug\panels\dump;

/**
 * Typed view-model for a single dump row consumed by the dumps grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\DumpPanel::save()} after every value has been narrowed to its
 * declared scalar/array type, so cell renderers can read typed properties without further `mixed` checks.
 */
final readonly class DumpRow
{
    public function __construct(
        /**
         * Highlighted dump payload as produced by {@see \yii\debug\panels\DumpPanel::varDump()}.
         */
        public string $message,
        /**
         * Log category attached to the dump call.
         */
        public string $category,
        /**
         * Capture timestamp in seconds since the Unix epoch (`0.0` when the original payload had no time).
         */
        public float $time,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured at the dump call site.
         */
        public array $trace,
    ) {}
}
