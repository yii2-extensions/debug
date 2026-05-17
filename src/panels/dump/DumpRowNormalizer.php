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
 * normalizer restores it once per row, so every cell renderer can read typed properties.
 */
final class DumpRowNormalizer
{
    /**
     * Builds a {@see DumpRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     *
     * @param mixed $data Raw row supplied by the GridView callback.
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
