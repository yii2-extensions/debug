<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Stub identity class for testing user authentication in the debug module.
 */
final class ArIdentity extends ActiveRecord implements IdentityInterface
{
    public static function findIdentity($id): IdentityInterface
    {
        return new self();
    }

    public static function findIdentityByAccessToken($token, $type = null): IdentityInterface|null
    {
        return null;
    }

    public function getAuthKey(): string
    {
        return 'auth-key';
    }

    public function getId(): int
    {
        return 1;
    }

    public static function tableName(): string
    {
        return 'stub_users';
    }

    public function validateAuthKey($authKey): bool
    {
        return $authKey === 'auth-key';
    }
}
