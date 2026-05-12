<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use yii\debug\helpers\Coerce;
use yii\debug\helpers\RowField;

use function in_array;
use function is_array;
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
            componentId: RowField::stringField($row, 'componentId'),
            driverName: RowField::stringField($row, 'driverName'),
            driverClass: RowField::stringField($row, 'driverClass'),
            isAsync: ($row['isAsync'] ?? false) === true,
            jobClass: RowField::stringField($row, 'jobClass'),
            payloadFields: self::payloadFields($row),
            time: RowField::floatField($row, 'time'),
            jobId: RowField::stringField($row, 'jobId'),
            ttr: RowField::nullableIntField($row, 'ttr'),
            delay: RowField::nullableIntField($row, 'delay'),
            priority: RowField::nullableIntField($row, 'priority'),
            attempt: RowField::nullableIntField($row, 'attempt'),
            duration: RowField::nullableFloatField($row, 'duration'),
            error: RowField::stringField($row, 'error'),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function eventTypeField(array $row): string
    {
        $value = $row['eventType'] ?? null;

        return is_string($value) && in_array($value, JobRecord::EVENT_TYPES, true) ? $value : JobRecord::TYPE_PUSH;
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

        return is_array($value) ? Coerce::stringKeyedArray($value) : [];
    }
}
