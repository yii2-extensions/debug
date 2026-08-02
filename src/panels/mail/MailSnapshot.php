<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

use yii\debug\storage\{PanelSnapshot, Payload};

use function array_map;
use function is_array;

/**
 * Canonical Mail panel snapshot holding the captured messages in their typed form.
 */
final readonly class MailSnapshot implements PanelSnapshot
{
    /**
     * @param list<MailMessage> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Narrows the captured `EVENT_AFTER_SEND` payloads into typed messages.
     *
     * @param array<array-key, mixed> $messages Captured payloads in send order; non-array entries are dropped.
     */
    public static function capture(array $messages): self
    {
        $entries = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $entries[] = MailMessage::fromCapture($message);
            }
        }

        return new self($entries);
    }

    /**
     * @return list<MailMessage> Captured messages in send order.
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
            $entries[] = MailMessage::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(MailMessage $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}
