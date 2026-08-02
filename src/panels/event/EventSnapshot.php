<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use yii\debug\storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical Event panel snapshot holding the captured rows in their typed form.
 */
final readonly class EventSnapshot implements PanelSnapshot
{
    /**
     * @param list<EventRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * @return list<EventRow> Captured rows in fire order.
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
            $entries[] = EventRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(EventRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}
