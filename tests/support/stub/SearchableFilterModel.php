<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\debug\models\search\UserSearchInterface;

/**
 * Filter-model stub implementing {@see UserSearchInterface}.
 */
final class SearchableFilterModel extends Model implements UserSearchInterface
{
    public function search(array $params): DataProviderInterface
    {
        return new ArrayDataProvider(['allModels' => []]);
    }
}
