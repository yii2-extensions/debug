<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\web\IdentityInterface;

/**
 * Plain identity stub.
 */
final class Identity implements IdentityInterface
{
    public function __construct(public int|string $id = 1) {}

    public static function findIdentity($id): IdentityInterface
    {
        return new self((int) $id);
    }

    public static function findIdentityByAccessToken($token, $type = null): IdentityInterface|null
    {
        return null;
    }

    public function getAuthKey(): string
    {
        return 'auth-key';
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function validateAuthKey($authKey): bool
    {
        return $authKey === 'auth-key';
    }
}
