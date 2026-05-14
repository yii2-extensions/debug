<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use yii\debug\helpers\{Coerce, RowField};
use yii\helpers\VarDumper;

use function is_array;
use function is_string;

/**
 * Narrows the loose `mixed` argument GridView passes to log-column callbacks into a typed {@see LogRow}.
 *
 * The log panel already produces typed rows, but the data provider erases the shape at the callback boundary. This
 * normalizer restores it once per row and pre-converts the originally-`mixed` message field into a display string, so
 * cell renderers never have to inspect the payload type.
 */
final class LogRowNormalizer
{
    /**
     * Builds a {@see LogRow} from an arbitrary value, falling back to defensible defaults for any field that is missing
     * or has the wrong type.
     *
     * @param mixed $data Raw row supplied by the GridView callback.
     */
    public static function from(mixed $data): LogRow
    {
        $row = is_array($data) ? $data : [];

        return new LogRow(
            id: RowField::intField($row, 'id'),
            message: self::messageField($row),
            level: RowField::intField($row, 'level'),
            category: RowField::stringField($row, 'category'),
            time: RowField::floatField($row, 'time'),
            timeOfPrevious: RowField::floatField($row, 'time_of_previous'),
            idOfPrevious: RowField::nullableIntField($row, 'id_of_previous'),
            idOfNext: RowField::nullableIntField($row, 'id_of_next'),
            trace: Coerce::traceFrames($row['trace'] ?? null),
        );
    }

    /**
     * Converts the originally-`mixed` log payload to a display string.
     *
     * Strings round-trip unchanged; non-strings are exported via {@see VarDumper} so the renderer can treat the message
     * as opaque text.
     *
     * @param array<array-key, mixed> $row Source row.
     */
    private static function messageField(array $row): string
    {
        $value = $row['message'] ?? null;

        return is_string($value) ? $value : VarDumper::export($value);
    }
}
