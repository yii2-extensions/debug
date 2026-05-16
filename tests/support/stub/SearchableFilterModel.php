<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;
use yii\data\{ArrayDataProvider, DataProviderInterface};
use yii\debug\models\search\UserSearchInterface;
use yii\web\IdentityInterface;

/**
 * Filter-model stub implementing {@see UserSearchInterface}.
 */
final class SearchableFilterModel extends Model implements UserSearchInterface
{
    public static function findIdentity($id): IdentityInterface|null
    {
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null): IdentityInterface|null
    {
        return null;
    }

    public function getAuthKey(): string
    {
        return '';
    }

    public function getId(): int
    {
        return 0;
    }
    public function search(array $params): DataProviderInterface
    {
        return new ArrayDataProvider(['allModels' => []]);
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }
}
