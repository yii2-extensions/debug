<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use yii\helpers\VarDumper;

use function is_array;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Narrows the loose `mixed` argument GridView passes to log-column callbacks into a typed {@see LogRow}.
 *
 * The log panel already produces typed rows, but the data provider erases the shape at the callback boundary. This
 * normalizer restores it once per row and pre-converts the originally-`mixed` message field into a display string, so
 * cell renderers never have to inspect the payload type.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => LogCellRenderer::renderTimeCell(LogRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class LogRowNormalizer
{
    /**
     * Builds a {@see LogRow} from an arbitrary value, falling back to defensible defaults for any field that is missing
     * or has the wrong type.
     */
    public static function from(mixed $data): LogRow
    {
        $row = is_array($data) ? $data : [];

        return new LogRow(
            id: self::intField($row, 'id'),
            message: self::messageField($row),
            level: self::intField($row, 'level'),
            category: self::stringField($row, 'category'),
            time: self::floatField($row, 'time'),
            timeOfPrevious: self::floatField($row, 'time_of_previous'),
            idOfPrevious: self::nullableIntField($row, 'id_of_previous'),
            idOfNext: self::nullableIntField($row, 'id_of_next'),
            trace: self::traceField($row),
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
     * Converts the originally-`mixed` log payload to a display string. Strings round-trip; non-strings are exported via
     * {@see VarDumper} so the renderer can treat the message as opaque text.
     *
     * @param array<array-key, mixed> $row
     */
    private static function messageField(array $row): string
    {
        $value = $row['message'] ?? null;

        return is_string($value) ? $value : VarDumper::export($value);
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
     * @param array<array-key, mixed> $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * Narrows the trace field to `list<array<string, mixed>>` so the renderer can iterate frames safely.
     *
     * @param array<array-key, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private static function traceField(array $row): array
    {
        $value = $row['trace'] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $frames = [];

        foreach ($value as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $normalized = [];

            foreach ($frame as $key => $entry) {
                if (is_string($key)) {
                    $normalized[$key] = $entry;
                }
            }

            $frames[] = $normalized;
        }

        return $frames;
    }
}
