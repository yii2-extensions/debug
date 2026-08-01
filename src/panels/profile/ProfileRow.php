<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use yii\debug\helpers\Coerce;

use function is_array;
use function max;

/**
 * Typed view-model for a single profile block consumed by the profile grid.
 *
 * Mirrors the shape produced by {@see \yii\debug\panels\ProfilingPanel} after every value has been narrowed, so
 * GridView callbacks read typed properties without further `mixed` narrowing at the data-provider boundary.
 */
final readonly class ProfileRow
{
    public function __construct(
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $timestamp,
        /**
         * Block execution time in milliseconds.
         */
        public float $duration,
        /**
         * Profile category attached to the `Yii::beginProfile()` token.
         */
        public string $category,
        /**
         * Profile token / informational label captured at `Yii::beginProfile()`.
         */
        public string $info,
        /**
         * Nesting level of the block, used to render an indentation arrow per level.
         */
        public int $level,
        /**
         * Zero-based sequence index assigned by the panel.
         */
        public int $seq,
    ) {}

    /**
     * Builds a typed profile row from a data-provider value.
     */
    public static function fromMixed(mixed $data): self
    {
        $row = is_array($data) ? $data : [];

        return new self(
            timestamp: Coerce::float($row['timestamp'] ?? null),
            duration: Coerce::float($row['duration'] ?? null),
            category: Coerce::string($row['category'] ?? null),
            info: Coerce::string($row['info'] ?? null),
            level: max(0, Coerce::int($row['level'] ?? null)),
            seq: Coerce::int($row['seq'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $models
     */
    public static function maxDuration(array $models): float
    {
        $maximum = 0.0;

        foreach ($models as $model) {
            $maximum = max($maximum, self::fromMixed($model)->duration);
        }

        return $maximum;
    }
}
