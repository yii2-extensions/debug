<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use yii\debug\helpers\Coerce;
use yii\helpers\VarDumper;

use function is_array;
use function is_string;

/**
 * Typed view-model for a single log row consumed by the logs grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\LogPanel::getModels()} after every value has been narrowed,
 * including the originally-`mixed` message converted to a display string at normalize time.
 */
final readonly class LogRow
{
    public function __construct(
        /**
         * One-based row id assigned by the panel.
         */
        public int $id,
        /**
         * Display string for the log payload (already `VarDumper::export()`ed when the source was non-string).
         */
        public string $message,
        /**
         * Logger level constant ({@see \yii\log\Logger}).
         */
        public int $level,
        /**
         * Log category attached to the message.
         */
        public string $category,
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $time,
        /**
         * Capture timestamp of the previous log row (also in milliseconds), or the same value as `$time` for the first
         * row of the request.
         */
        public float $timeOfPrevious,
        /**
         * Row id of the previous log entry, or `null` for the first row of the request.
         */
        public int|null $idOfPrevious,
        /**
         * Row id of the next log entry, or `null` for the last row of the request.
         */
        public int|null $idOfNext,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured at the log call site.
         */
        public array $trace,
    ) {}

    /**
     * Builds a typed log row from a data-provider value.
     */
    public static function fromMixed(mixed $data): self
    {
        $row = is_array($data) ? $data : [];
        $message = $row['message'] ?? null;

        return new self(
            id: Coerce::int($row['id'] ?? null),
            message: is_string($message) ? $message : VarDumper::export($message),
            level: Coerce::int($row['level'] ?? null),
            category: Coerce::string($row['category'] ?? null),
            time: Coerce::float($row['time'] ?? null),
            timeOfPrevious: Coerce::float($row['time_of_previous'] ?? null),
            idOfPrevious: Coerce::intOrNull($row['id_of_previous'] ?? null),
            idOfNext: Coerce::intOrNull($row['id_of_next'] ?? null),
            trace: Coerce::traceFrames($row['trace'] ?? null),
        );
    }
}
