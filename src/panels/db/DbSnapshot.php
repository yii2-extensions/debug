<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

use yii\debug\storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical database panel snapshot holding the resolved query rows in their typed form.
 */
final readonly class DbSnapshot implements PanelSnapshot
{
    /**
     * @param list<QueryRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * @return list<QueryRow> Executed statements in capture order.
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
            $entries[] = QueryRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(QueryRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}
