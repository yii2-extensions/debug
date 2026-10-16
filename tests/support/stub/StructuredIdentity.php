<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use Override;
use yii\base\Model;
use yii\web\IdentityInterface;

/**
 * Model identity exposing structured attributes for capture-policy regressions.
 */
final class StructuredIdentity extends Model implements IdentityInterface
{
    public int $id = 1;
    public string $password = 'SYNTHETIC_TOP_PASSWORD';
    /**
     * @var array<string, mixed>|object Nested identity attributes.
     */
    public array|object $profile = [];

    #[Override]
    public function attributeLabels(): array
    {
        return ['profile' => 'Profile details'];
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
        return 'synthetic-auth-key';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function validateAuthKey($authKey): bool
    {
        return $authKey === 'synthetic-auth-key';
    }
}
