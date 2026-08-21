<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use Override;
use yii\base\Model;
use yii\web\IdentityInterface;

final class SelectiveModelIdentity extends Model implements IdentityInterface
{
    public int $id = 1;
    public string $internal = 'not-an-attribute';

    #[Override]
    public function attributes(): array
    {
        return ['id'];
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
