<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;

use function array_map;

/**
 * Versioned envelope persisted for one captured request.
 */
final readonly class DebugSnapshot implements JsonSerializable
{
    public const int VERSION = 4;

    /**
     * @param array<string, array<string, mixed>> $panels
     * @param array<string, PanelFailure> $failures
     */
    public function __construct(
        public RequestSummary $summary,
        public array $panels,
        public array $failures,
    ) {}

    public static function fromArray(mixed $data): self
    {
        $payload = Payload::object($data)->shape(['version', 'summary', 'panels', 'failures']);

        if ($payload->int('version') !== self::VERSION) {
            throw HydrationException::at('$.version', 'storage version ' . self::VERSION);
        }

        $panels = [];

        foreach ($payload->map('panels') as $id => $panel) {
            $panels[$id] = Payload::object($panel, "$.panels.{$id}")->all();
        }

        $failures = [];

        foreach ($payload->map('failures') as $id => $failure) {
            $failures[$id] = PanelFailure::fromArray($failure, "$.failures.{$id}");
        }

        return new self(
            RequestSummary::fromArray($payload->raw('summary')),
            $panels,
            $failures,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => self::VERSION,
            'summary' => $this->summary->jsonSerialize(),
            'panels' => $this->panels,
            'failures' => array_map(
                static fn(PanelFailure $failure): array => $failure->jsonSerialize(),
                $this->failures,
            ),
        ];
    }
}
