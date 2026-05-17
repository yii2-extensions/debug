<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

/**
 * Typed view-model for a single database query row consumed by the queries grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\DbPanel} after every value has been narrowed, so GridView
 * callbacks read typed properties without further `mixed` narrowing.
 */
final readonly class QueryRow
{
    public function __construct(
        /**
         * Uppercase SQL command verb (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, ...).
         */
        public string $type,
        /**
         * Full SQL statement as emitted by the profile log.
         */
        public string $query,
        /**
         * Statement execution time in milliseconds.
         */
        public float $duration,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured for the statement.
         */
        public array $trace,
        /**
         * Stable hash of the backtrace, used to count caller duplicates.
         */
        public string $traceHash,
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $timestamp,
        /**
         * Zero-based sequence index assigned by the panel.
         */
        public int $seq,
        /**
         * Number of times the exact same query was emitted in this request (`>= 1`).
         */
        public int $duplicate,
        /**
         * Number of rows returned/affected, or `null` when the driver did not report it.
         */
        public int|null $rows,
    ) {}
}
