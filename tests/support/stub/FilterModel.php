<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;
use yii\data\{ArrayDataProvider, DataProviderInterface};

/**
 * Filter-model stub.
 */
final class FilterModel extends Model
{
    /**
     * @param array<int|string, mixed> $params Filter parameters.
     */
    public function search(array $params): DataProviderInterface
    {
        return new ArrayDataProvider(['allModels' => []]);
    }
}
