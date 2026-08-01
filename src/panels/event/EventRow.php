<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use yii\debug\helpers\Coerce;

use function count;
use function in_array;
use function is_array;

/**
 * Typed view-model for a single event row consumed by the events grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\EventPanel::save()} after every value has been narrowed, so
 * GridView callbacks read typed properties without further `mixed` narrowing at the data-provider boundary.
 */
final readonly class EventRow
{
    public function __construct(
        /**
         * Capture timestamp in seconds since the Unix epoch (`0.0` when the original payload had no time).
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
         * Stored as string so the value round-trips through the search model's `boolean` rule.
         */
        public string $isStatic,
        /**
         * Fully qualified class name of the sender, or `''` when the event was triggered statically.
         */
        public string $senderClass,
    ) {}

    /**
     * @param array<array-key, mixed> $models
     */
    public static function distinctClassCount(array $models): int
    {
        $classes = [];

        foreach ($models as $model) {
            $class = self::fromMixed($model)->class;

            if ($class !== '') {
                $classes[$class] = true;
            }
        }

        return count($classes);
    }

    /**
     * Builds a typed event row from a data-provider value.
     */
    public static function fromMixed(mixed $data): self
    {
        $row = is_array($data) ? $data : [];

        return new self(
            time: Coerce::float($row['time'] ?? null),
            name: Coerce::string($row['name'] ?? null),
            class: Coerce::string($row['class'] ?? null),
            isStatic: in_array($row['isStatic'] ?? null, ['1', 1, true], true) ? '1' : '0',
            senderClass: Coerce::string($row['senderClass'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $models
     */
    public static function staticCount(array $models): int
    {
        $static = 0;

        foreach ($models as $model) {
            if (self::fromMixed($model)->isStatic === '1') {
                $static++;
            }
        }

        return $static;
    }
}
