<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;
use function is_array;

/**
 * Canonical Queue panel snapshot holding the captured job events in their typed form.
 */
final readonly class QueueSnapshot implements PanelSnapshot
{
    /**
     * @param list<JobRecord> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Narrows the captured queue-event payloads into typed records.
     *
     * @param array<array-key, mixed> $records Captured payloads in event order; non-array entries are dropped.
     */
    public static function capture(array $records): self
    {
        $entries = [];

        foreach ($records as $record) {
            if (is_array($record)) {
                $entries[] = JobRecord::fromCapture($record);
            }
        }

        return new self($entries);
    }

    /**
     * @return list<JobRecord> Captured job events in event order.
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
            $entries[] = JobRecord::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(JobRecord $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}
