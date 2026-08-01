<?php

declare(strict_types=1);

namespace yii\debug\panels\event;

use yii\debug\helpers\RowField;

use function count;
use function in_array;
use function is_array;

/**
 * Narrows the loose `mixed` argument GridView passes to event-column callbacks into a typed {@see EventRow}.
 *
 * The event panel already saves typed rows, but the data provider erases the shape at the callback boundary. This
 * normalizer restores it once per row, so every cell renderer can read typed properties without further runtime checks.
 */
final class EventRowNormalizer
{
    /**
     * Counts the distinct event classes across the given rows, ignoring rows without a class.
     *
     * @param array<array-key, mixed> $models Raw rows supplied by the data provider.
     *
     * @return int Number of unique non-empty `class` values.
     */
    public static function distinctClassCount(array $models): int
    {
        $classes = [];

        foreach ($models as $model) {
            $class = self::from($model)->class;

            if ($class !== '') {
                $classes[$class] = true;
            }
        }

        return count($classes);
    }

    /**
     * Builds an {@see EventRow} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     *
     * @param mixed $data Raw row supplied by the GridView callback.
     */
    public static function from(mixed $data): EventRow
    {
        $row = is_array($data) ? $data : [];

        return new EventRow(
            time: RowField::floatField($row, 'time'),
            name: RowField::stringField($row, 'name'),
            class: RowField::stringField($row, 'class'),
            isStatic: self::isStaticField($row),
            senderClass: RowField::stringField($row, 'senderClass'),
        );
    }

    /**
     * Counts the rows flagged as class-level (static) events.
     *
     * @param array<array-key, mixed> $models Raw rows supplied by the data provider.
     *
     * @return int Number of rows normalized with `isStatic` `'1'`.
     */
    public static function staticCount(array $models): int
    {
        $static = 0;

        foreach ($models as $model) {
            if (self::from($model)->isStatic === '1') {
                $static++;
            }
        }

        return $static;
    }

    /**
     * Narrows the `isStatic` field to the canonical `'0'` / `'1'` strings used by the search model's boolean rule.
     *
     * Any non-`'1'` value collapses to `'0'`.
     *
     * @param array<array-key, mixed> $row Source row.
     */
    private static function isStaticField(array $row): string
    {
        $value = $row['isStatic'] ?? null;

        return in_array($value, ['1', 1, true], true) ? '1' : '0';
    }
}
