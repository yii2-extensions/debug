<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;

use function array_map;

/**
 * Versioned request-summary index.
 */
final readonly class Manifest implements JsonSerializable
{
    /**
     * @param array<string, RequestSummary> $entries
     */
    public function __construct(public array $entries) {}

    public static function fromArray(mixed $data): self
    {
        $payload = Payload::object($data)->shape(['version', 'entries']);

        if ($payload->int('version') !== DebugSnapshot::VERSION) {
            throw HydrationException::at('$.version', 'storage version ' . DebugSnapshot::VERSION);
        }

        $entries = [];

        foreach ($payload->map('entries') as $tag => $entry) {
            $summary = RequestSummary::fromArray($entry, "$.entries.{$tag}");

            if ($summary->tag !== $tag) {
                throw HydrationException::at("$.entries.{$tag}.tag", "the manifest key '{$tag}'");
            }

            $entries[$tag] = $summary;
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => DebugSnapshot::VERSION,
            'entries' => array_map(
                static fn(RequestSummary $summary): array => $summary->jsonSerialize(),
                $this->entries,
            ),
        ];
    }
}
