<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use function in_array;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * Narrows the loose `mixed` argument GridView passes to event-column callbacks into a typed {@see EventRow}.
 *
 * The event panel already saves typed rows, but the data provider erases the shape at the callback boundary. This
 * normalizer restores it once per row so every cell renderer can read typed properties without further runtime
 * checks.
 *
 * Usage example:
 * ```php
 * 'value' => static fn(mixed $data): string => EventCellRenderer::renderTimeCell(EventRowNormalizer::from($data)),
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class EventRowNormalizer
{
    /**
     * Builds an {@see EventRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     */
    public static function from(mixed $data): EventRow
    {
        $row = is_array($data) ? $data : [];

        return new EventRow(
            time: self::floatField($row, 'time'),
            name: self::stringField($row, 'name'),
            class: self::stringField($row, 'class'),
            isStatic: self::isStaticField($row),
            senderClass: self::stringField($row, 'senderClass'),
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
     * Narrows the `isStatic` field to the canonical '0' / '1' strings used by the search model's boolean rule.
     *
     * Any non-'1' value collapses to '0'.
     *
     * @param array<array-key, mixed> $row
     */
    private static function isStaticField(array $row): string
    {
        $value = $row['isStatic'] ?? null;

        return in_array($value, ['1', 1, true], true) ? '1' : '0';
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
