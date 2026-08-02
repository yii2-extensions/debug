<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use yii\debug\storage\{PanelRow, Payload};

use function count;

/**
 * Typed event row recorded by the wildcard listener and persisted in that form.
 */
final readonly class EventRow implements PanelRow
{
    public function __construct(
        /**
         * Capture timestamp in seconds since the Unix epoch.
         */
        public float $time,
        /**
         * Event name (for example, `EVENT_AFTER_REQUEST`).
         */
        public string $name,
        /**
         * Fully qualified class name of the event object.
         */
        public string $class,
        /**
         * `'1'` when the event was triggered statically (no sender), `'0'` otherwise.
         *
         * Stored as a string so the value round-trips through the search model's `boolean` rule.
         */
        public string $isStatic,
        /**
         * Fully qualified class name of the sender, or `''` when the event was triggered statically.
         */
        public string $senderClass,
    ) {}

    /**
     * Returns how many distinct event classes the given rows cover.
     *
     * @param list<self> $rows Captured event rows.
     */
    public static function distinctClassCount(array $rows): int
    {
        $classes = [];

        foreach ($rows as $row) {
            if ($row->class !== '') {
                $classes[$row->class] = true;
            }
        }

        return count($classes);
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['time', 'name', 'class', 'isStatic', 'senderClass']);

        return new self(
            time: $payload->number('time'),
            name: $payload->string('name'),
            class: $payload->string('class'),
            isStatic: $payload->string('isStatic'),
            senderClass: $payload->string('senderClass'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'time' => $this->time,
            'name' => $this->name,
            'class' => $this->class,
            'isStatic' => $this->isStatic,
            'senderClass' => $this->senderClass,
        ];
    }

    /**
     * Returns how many of the given rows were triggered statically.
     *
     * @param list<self> $rows Captured event rows.
     */
    public static function staticCount(array $rows): int
    {
        $static = 0;

        foreach ($rows as $row) {
            if ($row->isStatic === '1') {
                $static++;
            }
        }

        return $static;
    }
}
