<?php

declare(strict_types=1);

namespace yii\debug\panels\dump;

use yii\debug\storage\{PanelSnapshot, Payload};

use function array_map;
use function is_array;

/**
 * Canonical Dump panel snapshot holding the captured rows in their typed form.
 */
final readonly class DumpSnapshot implements PanelSnapshot
{
    /**
     * @param list<DumpRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Narrows the raw logger tuples into typed rows.
     *
     * @param array<int|string, mixed> $messages Logger tuples in capture order; non-array entries are dropped.
     */
    public static function capture(array $messages): self
    {
        $entries = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $entries[] = DumpRow::fromLoggerTuple($message);
            }
        }

        return new self($entries);
    }

    /**
     * @return list<DumpRow> Captured rows in capture order.
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
            $entries[] = DumpRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(DumpRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}
