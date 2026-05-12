<?php

declare(strict_types=1);

namespace yii\debug\panels\dump;

use yii\debug\helpers\Coerce;
use yii\debug\helpers\RowField;

use function is_array;

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
            message: RowField::stringField($row, 'message'),
            category: RowField::stringField($row, 'category'),
            time: RowField::floatField($row, 'time'),
            trace: Coerce::traceFrames($row['trace'] ?? null),
        );
    }
}
