<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use yii\debug\helpers\{Coerce, RowField};

use function in_array;
use function is_array;
use function is_string;

/**
 * Narrows a raw row from `QueuePanel::save()` into a typed {@see JobRecord}.
 *
 * The panel stores each captured lifecycle event as a positional array; this normalizer is the single boundary where
 * those arrays become typed objects, so the renderer never inspects raw payloads.
 */
final class JobRecordNormalizer
{
    /**
     * Builds a {@see JobRecord} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     *
     * The `eventType` is normalized to one of `'push'` / `'exec'` / `'error'`; unknown values collapse to `'push'`
     * so the renderer always has a sane default.
     *
     * @param mixed $data Raw row from {@see \yii\debug\panels\QueuePanel::$data}.
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
     * Reads `$row['eventType']` and clamps it to one of {@see JobRecord::EVENT_TYPES}, defaulting to `'push'`.
     *
     * @param array<array-key, mixed> $row Source row.
     */
    private static function eventTypeField(array $row): string
    {
        $value = $row['eventType'] ?? null;

        return is_string($value) && in_array($value, JobRecord::EVENT_TYPES, true) ? $value : JobRecord::TYPE_PUSH;
    }

    /**
     * Narrows the saved payload fields to `array<string, mixed>`.
     *
     * Top-level keys are property names and must be strings; non-string keys are dropped. Nested values keep their
     * original shape, so arrays with int keys inside are left untouched.
     *
     * @param array<array-key, mixed> $row Source row.
     *
     * @return array<string, mixed> Payload map with the top-level FQCN expanded recursively in nested entries.
     */
    private static function payloadFields(array $row): array
    {
        $value = $row['payloadFields'] ?? null;

        return is_array($value) ? Coerce::stringKeyedArray($value) : [];
    }
}
