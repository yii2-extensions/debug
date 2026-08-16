<?php

declare(strict_types=1);

namespace yii\debug\panels\dump;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Storage\{PanelRow, Payload};

/**
 * Typed dump row narrowed once from the Yii logger tuple and persisted in that form.
 *
 * The payload is already rendered by {@see \yii\debug\panels\DumpPanel::varDump()} at capture time, so the detail view
 * renders it without re-serializing.
 */
final readonly class DumpRow implements PanelRow
{
    public function __construct(
        /**
         * Highlighted dump payload as produced by {@see \yii\debug\panels\DumpPanel::varDump()}.
         */
        public string $message,
        /**
         * Logger level constant ({@see \yii\log\Logger}).
         */
        public int $level,
        /**
         * Log category attached to the dump call.
         */
        public string $category,
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $time,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured at the dump call site.
         */
        public array $trace,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['message', 'level', 'category', 'time', 'trace']);

        return new self(
            message: $payload->string('message'),
            level: $payload->int('level'),
            category: $payload->string('category'),
            time: $payload->number('time'),
            trace: $payload->rows('trace'),
        );
    }

    /**
     * Narrows one raw Yii logger tuple into a typed row.
     *
     * @param array<int|string, mixed> $message Logger tuple `[message, level, category, timestamp, traces]`.
     */
    public static function fromLoggerTuple(array $message): self
    {
        return new self(
            message: Coerce::stringOrNull($message[0] ?? null) ?? '',
            level: Coerce::intOrNull($message[1] ?? null) ?? 0,
            category: Coerce::stringOrNull($message[2] ?? null) ?? '',
            time: (Coerce::floatOrNull($message[3] ?? null) ?? 0.0) * 1000,
            trace: Coerce::traceFrames($message[4] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'category' => $this->category,
            'time' => $this->time,
            'trace' => $this->trace,
        ];
    }
}
