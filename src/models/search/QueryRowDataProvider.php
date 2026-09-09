<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Panel\Db\QueryRow;
use yii\data\{ArrayDataProvider, Sort};
use yii\helpers\ArrayHelper;

/**
 * Sorts immutable query rows by their persisted fields without requiring public properties.
 */
final class QueryRowDataProvider extends ArrayDataProvider
{
    /**
     * @param array<array-key, mixed> $models Rows accepted by the base data provider.
     * @param Sort $sort Active field ordering.
     *
     * @return array<array-key, mixed> Sorted rows, preserving their original instances.
     */
    #[Override]
    protected function sortModels($models, $sort): array
    {
        $orders = $sort->getOrders();
        $keys = [];

        foreach (array_keys($orders) as $attribute) {
            $keys[] = static fn(array|object $row): mixed => ArrayHelper::getValue(
                $row instanceof QueryRow ? $row->jsonSerialize() : $row,
                (string) $attribute,
            );
        }

        ArrayHelper::multisort(
            $models,
            $keys,
            array_values($orders),
            $sort->sortFlags,
        );

        return $models;
    }
}
