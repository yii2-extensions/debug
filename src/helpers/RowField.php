<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * Narrows `mixed` row entries into the typed scalars row-normalizers expect.
 *
 * GridView callbacks receive every row as `array<array-key, mixed>`; the normalizer layer used to repeat the same
 * {@see is_numeric()} / {@see is_int()} / {@see is_string()} defensive ladders in every panel. This helper centralises
 * that pattern so the row narrowing behaves identically across panels.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RowField
{
    /**
     * Returns the numeric value at `$key` cast to `float`, or `0.0` when missing/non-numeric.
     *
     * @param array<array-key, mixed> $row
     */
    public static function floatField(array $row, string $key): float
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Returns the numeric value at `$key` cast to `int`, or `0` when missing/non-numeric.
     *
     * @param array<array-key, mixed> $row
     */
    public static function intField(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Returns the numeric value at `$key` cast to `float`, or `null` when missing/non-numeric.
     *
     * @param array<array-key, mixed> $row
     */
    public static function nullableFloatField(array $row, string $key): float|null
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns the numeric value at `$key` cast to `int`, or `null` when missing/non-numeric.
     *
     * @param array<array-key, mixed> $row
     */
    public static function nullableIntField(array $row, string $key): int|null
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Returns the string value at `$key`, or `''` when missing or not a string.
     *
     * @param array<array-key, mixed> $row
     */
    public static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
