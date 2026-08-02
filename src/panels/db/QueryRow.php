<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

use yii\debug\storage\{PanelRow, Payload};

use function array_values;
use function max;

/**
 * Typed view-model for a single database query row consumed by the queries grid.
 *
 * Resolved once at capture time from the logger timings, then persisted in that form: the SQL verb, the statement,
 * its duration, the backtrace and its hash, the duplicate count, and the reported row count.
 */
final readonly class QueryRow implements PanelRow
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

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'type',
                    'query',
                    'duration',
                    'trace',
                    'traceHash',
                    'timestamp',
                    'seq',
                    'duplicate',
                    'rows',
                ],
            );

        return new self(
            type: $payload->string('type'),
            query: $payload->string('query'),
            duration: $payload->number('duration'),
            trace: $payload->rows('trace'),
            traceHash: $payload->string('traceHash'),
            timestamp: $payload->number('timestamp'),
            seq: $payload->int('seq'),
            duplicate: max(1, $payload->int('duplicate')),
            rows: $payload->nullableInt('rows'),
        );
    }

    /**
     * Builds a typed row from one resolved logger timing.
     *
     * @param array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: array<int, array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * } $timing Resolved timing.
     * @param string $type Uppercase SQL command verb extracted from the statement.
     * @param int $seq Zero-based sequence index.
     * @param int $duplicate Number of times the same statement was emitted in this request.
     * @param int|null $rows Rows reported by the driver, or `null` when it reported none.
     */
    public static function fromTiming(array $timing, string $type, int $seq, int $duplicate, int|null $rows): self
    {
        return new self(
            type: $type,
            query: $timing['info'],
            duration: $timing['duration'] * 1000,
            trace: array_values($timing['trace']),
            traceHash: $timing['traceHash'],
            timestamp: $timing['timestamp'] * 1000,
            seq: $seq,
            duplicate: max(1, $duplicate),
            rows: $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'query' => $this->query,
            'duration' => $this->duration,
            'trace' => $this->trace,
            'traceHash' => $this->traceHash,
            'timestamp' => $this->timestamp,
            'seq' => $this->seq,
            'duplicate' => $this->duplicate,
            'rows' => $this->rows,
        ];
    }
}
