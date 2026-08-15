<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};
use yii\log\Logger;

use function array_map;
use function is_string;

/**
 * Canonical routing trace snapshot, with the URL-rule trace resolved into typed rows at capture time.
 */
final readonly class RouterSnapshot implements PanelSnapshot
{
    /**
     * @param list<CurrentRouteLogRow> $entries
     */
    public function __construct(
        public string|null $action,
        public string $route,
        public string|null $message,
        private array $entries,
    ) {}

    /**
     * Replays the URL-manager trace into typed rows, dropping the duplicate emitted by nested REST rules.
     *
     * @param array<int, array<int|string, mixed>> $messages Raw routing log tuples in capture order.
     */
    public static function capture(string|null $action, array $messages, string $route): self
    {
        $entries = [];
        $message = null;
        $last = null;

        foreach ($messages as $tuple) {
            if (($tuple[1] ?? null) === Logger::LEVEL_TRACE && is_string($tuple[0] ?? null)) {
                $message = $tuple[0];

                continue;
            }

            $row = CurrentRouteLogRow::fromLogMessage($tuple[0] ?? null);

            if ($row === null) {
                continue;
            }

            if ($last !== null && $last->parent !== '' && $last->parent === $row->rule) {
                continue;
            }

            $entries[] = $row;
            $last = $row;
        }

        return new self($action, $route, $message, $entries);
    }

    /**
     * @return list<CurrentRouteLogRow> Rules inspected during routing, in inspection order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['action', 'route', 'message', 'entries']);
        $entries = [];

        foreach ($payload->list('entries') as $index => $entry) {
            $entries[] = CurrentRouteLogRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self(
            $payload->nullableString('action'),
            $payload->string('route'),
            $payload->nullableString('message'),
            $entries,
        );
    }

    /**
     * Returns whether any inspected rule reported a successful match.
     */
    public function hasMatch(): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->match) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action,
            'route' => $this->route,
            'message' => $this->message,
            'entries' => array_map(
                static fn(CurrentRouteLogRow $row): array => $row->jsonSerialize(),
                $this->entries,
            ),
        ];
    }
}
