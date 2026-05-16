<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;
use yii\web\IdentityInterface;

/**
 * Model-based identity stub.
 */
final class ModelIdentity extends Model implements IdentityInterface
{
    public int $id = 1;
    public string $username = 'wilmer';

    public function attributeLabels(): array
    {
        return ['id' => 'Id', 'username' => 'Username'];
    }

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
        return $this->id;
    }

    public function validateAuthKey($authKey): bool
    {
        return $authKey === 'auth-key';
    }
}
