<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

use function is_array;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Narrows the loose `mixed` argument GridView passes to column callbacks into a typed {@see QueryRow}.
 *
 * The DB panel already produces typed rows (`array{type, query, duration, ...}`), but the data provider erases that
 * shape at the callback boundary. This normalizer restores it once per row so every cell renderer can read typed
 * properties.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => DbQueryRenderer::renderTypeCell(QueryRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueryRowNormalizer
{
    /**
     * Builds a {@see QueryRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     */
    public static function from(mixed $data): QueryRow
    {
        $row = is_array($data) ? $data : [];

        return new QueryRow(
            type: self::stringField($row, 'type'),
            query: self::stringField($row, 'query'),
            duration: self::floatField($row, 'duration'),
            trace: self::traceField($row),
            traceHash: self::stringField($row, 'traceHash'),
            timestamp: self::floatField($row, 'timestamp'),
            seq: self::intField($row, 'seq'),
            duplicate: max(1, self::intField($row, 'duplicate')),
            rows: self::nullableIntField($row, 'rows'),
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
     * Narrows the trace field to `list<array<string, mixed>>` so cell renderers can iterate frames safely.
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
