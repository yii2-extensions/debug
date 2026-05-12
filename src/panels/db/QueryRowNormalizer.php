<?php

declare(strict_types=1);

namespace yii\debug\panels\db;

use yii\debug\helpers\Coerce;
use yii\debug\helpers\RowField;

use function is_array;
use function max;

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
            type: RowField::stringField($row, 'type'),
            query: RowField::stringField($row, 'query'),
            duration: RowField::floatField($row, 'duration'),
            trace: Coerce::traceFrames($row['trace'] ?? null),
            traceHash: RowField::stringField($row, 'traceHash'),
            timestamp: RowField::floatField($row, 'timestamp'),
            seq: RowField::intField($row, 'seq'),
            duplicate: max(1, RowField::intField($row, 'duplicate')),
            rows: RowField::nullableIntField($row, 'rows'),
        );
    }
}
