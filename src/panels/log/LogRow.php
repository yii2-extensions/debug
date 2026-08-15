<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use PHPForge\Debug\Storage\{PanelRow, Payload};
use yii\debug\helpers\Coerce;
use yii\helpers\VarDumper;

use function is_string;

/**
 * Typed log row narrowed once from the Yii logger tuple and persisted in that form.
 *
 * The grid, the search model, and the timeline memory chart all read these properties directly, so no `mixed` value
 * survives past capture.
 */
final readonly class LogRow implements PanelRow
{
    public function __construct(
        /**
         * One-based row id assigned in capture order.
         */
        public int $id,
        /**
         * Display string for the log payload, exported via {@see VarDumper::export()} when the source was not a string.
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
         * Capture timestamp of the previous row in milliseconds, or this row's own time for the first row.
         */
        public float $timeOfPrevious,
        /**
         * Seconds elapsed between the previous row and this one.
         */
        public float $timeSincePrevious,
        /**
         * Row id of the previous entry, or `null` for the first row of the request.
         */
        public int|null $idOfPrevious,
        /**
         * Row id of the next entry, or `null` for the last row of the request.
         */
        public int|null $idOfNext,
        /**
         * Memory usage in bytes reported by the logger, or `0` when the logger omitted it.
         */
        public int $memory,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured at the log call site.
         */
        public array $trace,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'id',
                    'message',
                    'level',
                    'category',
                    'time',
                    'timeOfPrevious',
                    'timeSincePrevious',
                    'idOfPrevious',
                    'idOfNext',
                    'memory',
                    'trace',
                ],
            );

        return new self(
            id: $payload->int('id'),
            message: $payload->string('message'),
            level: $payload->int('level'),
            category: $payload->string('category'),
            time: $payload->number('time'),
            timeOfPrevious: $payload->number('timeOfPrevious'),
            timeSincePrevious: $payload->number('timeSincePrevious'),
            idOfPrevious: $payload->nullableInt('idOfPrevious'),
            idOfNext: $payload->nullableInt('idOfNext'),
            memory: $payload->int('memory'),
            trace: $payload->rows('trace'),
        );
    }

    /**
     * Narrows one raw Yii logger tuple into a typed row.
     *
     * @param array<int|string, mixed> $message Logger tuple `[message, level, category, timestamp, traces, memory]`.
     * @param int $id One-based row id assigned in capture order.
     * @param float $timeOfPrevious Timestamp of the previous row in seconds; this row's own timestamp for the first.
     * @param int|null $idOfPrevious Row id preceding this one, or `null` for the first row.
     * @param int|null $idOfNext Row id following this one, or `null` for the last row.
     */
    public static function fromLoggerTuple(
        array $message,
        int $id,
        float $timeOfPrevious,
        int|null $idOfPrevious,
        int|null $idOfNext,
    ): self {
        $payload = $message[0] ?? null;

        $timestamp = Coerce::floatOrNull($message[3] ?? null) ?? 0.0;

        return new self(
            id: $id,
            message: is_string($payload) ? $payload : VarDumper::export($payload),
            level: Coerce::intOrNull($message[1] ?? null) ?? 0,
            category: Coerce::stringOrNull($message[2] ?? null) ?? '',
            time: $timestamp * 1000,
            timeOfPrevious: $timeOfPrevious * 1000,
            timeSincePrevious: $timestamp - $timeOfPrevious,
            idOfPrevious: $idOfPrevious,
            idOfNext: $idOfNext,
            memory: Coerce::intOrNull($message[5] ?? null) ?? 0,
            trace: Coerce::traceFrames($message[4] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'level' => $this->level,
            'category' => $this->category,
            'time' => $this->time,
            'timeOfPrevious' => $this->timeOfPrevious,
            'timeSincePrevious' => $this->timeSincePrevious,
            'idOfPrevious' => $this->idOfPrevious,
            'idOfNext' => $this->idOfNext,
            'memory' => $this->memory,
            'trace' => $this->trace,
        ];
    }
}
