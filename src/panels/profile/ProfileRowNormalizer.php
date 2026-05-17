<?php

declare(strict_types=1);

namespace yii\debug\panels\profile;

use yii\debug\helpers\RowField;

use function is_array;
use function max;

/**
 * Narrows the loose `mixed` argument GridView passes to profile-column callbacks into a typed {@see ProfileRow}.
 *
 * The profiling panel already produces typed rows, but the data provider erases the shape at the callback boundary.
 *
 * This normalizer restores it once per row, so every cell renderer can read typed properties without further runtime
 * checks.
 */
final class ProfileRowNormalizer
{
    /**
     * Builds a {@see ProfileRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     *
     * @param mixed $data Raw row supplied by the GridView callback.
     */
    public static function from(mixed $data): ProfileRow
    {
        $row = is_array($data) ? $data : [];

        return new ProfileRow(
            timestamp: RowField::floatField($row, 'timestamp'),
            duration: RowField::floatField($row, 'duration'),
            category: RowField::stringField($row, 'category'),
            info: RowField::stringField($row, 'info'),
            level: max(0, RowField::intField($row, 'level')),
            seq: RowField::intField($row, 'seq'),
        );
    }
}
