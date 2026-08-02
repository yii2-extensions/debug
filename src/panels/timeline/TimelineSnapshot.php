<?php

declare(strict_types=1);

namespace yii\debug\panels\timeline;

use yii\debug\storage\{PanelSnapshot, Payload};

/**
 * Canonical timing and peak-memory snapshot for the Timeline panel.
 */
final readonly class TimelineSnapshot implements PanelSnapshot
{
    public function __construct(
        public float $start,
        public float $end,
        public int $memory,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'start',
                    'end',
                    'memory',
                ],
            );

        return new self(
            $payload->number('start'),
            $payload->number('end'),
            $payload->int('memory'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'memory' => $this->memory,
        ];
    }
}
