<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Narrows a raw row from `QueuePanel::save()` into a typed {@see JobRecord}.
 *
 * The panel stores each captured lifecycle event as a positional array; this normalizer is the single boundary where
 * those arrays become typed objects, so the renderer never inspects raw payloads.
 *
 * Usage example:
 * ```php
 * $record = JobRecordNormalizer::from($row);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class JobRecordNormalizer
{
    private const array EVENT_TYPES = ['push', 'exec', 'error'];

    /**
     * Builds a {@see JobRecord} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type. The `eventType` is normalised to one of `'push'` / `'exec'` / `'error'`;
     * unknown values collapse to `'push'` so the renderer always has a sane default.
     */
    public static function from(mixed $data): JobRecord
    {
        $row = is_array($data) ? $data : [];

        return new JobRecord(
            eventType: self::eventTypeField($row),
            componentId: self::stringField($row, 'componentId'),
            driverName: self::stringField($row, 'driverName'),
            driverClass: self::stringField($row, 'driverClass'),
            isAsync: ($row['isAsync'] ?? false) === true,
            jobClass: self::stringField($row, 'jobClass'),
            payloadFields: self::payloadFields($row),
            time: self::floatField($row, 'time'),
            jobId: self::stringField($row, 'jobId'),
            ttr: self::nullableIntField($row, 'ttr'),
            delay: self::nullableIntField($row, 'delay'),
            priority: self::nullableIntField($row, 'priority'),
            attempt: self::nullableIntField($row, 'attempt'),
            duration: self::nullableFloatField($row, 'duration'),
            error: self::stringField($row, 'error'),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function eventTypeField(array $row): string
    {
        $value = $row['eventType'] ?? null;

        return is_string($value) && in_array($value, self::EVENT_TYPES, true) ? $value : 'push';
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
    private static function nullableFloatField(array $row, string $key): float|null
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function nullableIntField(array $row, string $key): int|null
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Narrows the saved payload fields to `array<string, mixed>` (top-level keys are property names; always strings;
     * non-string keys are dropped). Nested values keep their original shape, so arrays with int keys inside are left
     * untouched.
     *
     * @param array<array-key, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function payloadFields(array $row): array
    {
        $value = $row['payloadFields'] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $key => $field) {
            if (is_string($key)) {
                $out[$key] = $field;
            }
        }

        return $out;
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
