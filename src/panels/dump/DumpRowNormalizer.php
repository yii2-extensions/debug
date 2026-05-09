<?php

declare(strict_types=1);

namespace yii\debug\panels\dump;

use function is_array;
use function is_numeric;
use function is_string;

/**
 * Narrows the loose `mixed` argument GridView passes to dump-column callbacks into a typed {@see DumpRow}.
 *
 * The dump panel already saves typed messages, but the data provider erases the shape at the callback boundary. This
 * normalizer restores it once per row so every cell renderer can read typed properties.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data, $key, int $index): string => DumpCardRenderer::renderMessageCell(
 *     DumpRowNormalizer::from($data),
 *     $panel,
 *     $index,
 * ),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class DumpRowNormalizer
{
    /**
     * Builds a {@see DumpRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     */
    public static function from(mixed $data): DumpRow
    {
        $row = is_array($data) ? $data : [];

        return new DumpRow(
            message: self::stringField($row, 'message'),
            category: self::stringField($row, 'category'),
            time: self::floatField($row, 'time'),
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
