<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use Stringable;

use function is_array;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;

/**
 * Narrows arbitrary `mixed` payloads into the typed scalars debug-panel renderers expect.
 *
 * Snapshots and event payloads cross the `mixed` boundary repeatedly (saved files, GridView callbacks, panel `$data`
 * arrays). Every panel used to keep a private `stringValue()` helper for the same narrowing pattern; this class
 * centralises that logic so the same coercion behaves identically across panels.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class Coerce
{
    /**
     * Returns the value as a `float` when it is numeric, `null` otherwise.
     */
    public static function floatOrNull(mixed $value): float|null
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns the value as an `int` when it is an integer or numeric, `null` otherwise.
     */
    public static function intOrNull(mixed $value): int|null
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Returns only the entries of `$data` whose key is a `string`, preserving order.
     *
     * Used when narrowing a `mixed`/`array<array-key, mixed>` snapshot down to the `array<string, mixed>` shape the
     * downstream view-model normalizers expect.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function stringKeyedArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
    /**
     * Returns the value as a string when it is scalar or {@see Stringable}, `null` otherwise.
     */
    public static function stringOrNull(mixed $value): string|null
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Narrows a raw trace value (as captured by Yii's logger) into the `list<array<string, mixed>>` shape every panel
     * renderer consumes.
     *
     * Each frame keeps only its `string`-keyed entries; non-array frames are dropped.
     *
     * @return list<array<string, mixed>>
     */
    public static function traceFrames(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $frames = [];

        foreach ($value as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $frames[] = self::stringKeyedArray($frame);
        }

        return $frames;
    }
}
