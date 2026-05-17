<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\debug\models\search\UserSearchInterface;

/**
 * Stub model that implements the `UserSearchInterface` to test the behavior of the debug module when a model has search
 * capabilities.
 */
final class SearchableFilterModel extends Model implements UserSearchInterface
{
    public function search(array $params): DataProviderInterface
    {
        return new ArrayDataProvider(['allModels' => []]);
    }
}
