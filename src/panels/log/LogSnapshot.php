<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};
use yii\debug\helpers\Coerce;

use function array_map;
use function count;
use function is_array;

/**
 * Canonical Log panel snapshot holding the captured rows in their typed form.
 */
final readonly class LogSnapshot implements PanelSnapshot
{
    /**
     * @param list<LogRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Narrows the raw logger tuples into typed rows, deriving the previous/next links and the inter-row deltas.
     *
     * @param array<int|string, mixed> $messages Logger tuples in capture order; non-array entries are dropped.
     */
    public static function capture(array $messages): self
    {
        $tuples = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $tuples[] = $message;
            }
        }

        $entries = [];

        $count = count($tuples);

        $previousId = null;
        $previousTime = null;

        foreach ($tuples as $index => $message) {
            $id = $index + 1;

            $timestamp = Coerce::floatOrNull($message[3] ?? null) ?? 0.0;

            $previousTime ??= $timestamp;

            $entries[] = LogRow::fromLoggerTuple(
                $message,
                $id,
                $previousTime,
                $previousId,
                $id < $count ? $id + 1 : null,
            );

            $previousId = $id;
            $previousTime = $timestamp;
        }

        return new self($entries);
    }

    /**
     * @return list<LogRow> Captured rows in capture order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['entries']);

        $entries = [];

        foreach ($payload->list('entries') as $index => $entry) {
            $entries[] = LogRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(LogRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}
