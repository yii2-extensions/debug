<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;

/**
 * Narrows the loose `mixed` argument GridView passes to profile-column callbacks into a typed {@see ProfileRow}.
 *
 * The profiling panel already produces typed rows, but the data provider erases the shape at the callback boundary.
 * This normalizer restores it once per row so every cell renderer can read typed properties without further runtime
 * checks.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => ProfileCellRenderer::renderTimeCell(ProfileRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class ProfileRowNormalizer
{
    /**
     * Builds a {@see ProfileRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     */
    public static function from(mixed $data): ProfileRow
    {
        $row = is_array($data) ? $data : [];

        return new ProfileRow(
            timestamp: self::floatField($row, 'timestamp'),
            duration: self::floatField($row, 'duration'),
            category: self::stringField($row, 'category'),
            info: self::stringField($row, 'info'),
            level: max(0, self::intField($row, 'level')),
            seq: self::intField($row, 'seq'),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function floatField(array $row, string $key): float
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function intField(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
