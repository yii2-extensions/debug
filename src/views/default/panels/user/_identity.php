<?php

declare(strict_types=1);

use yii\debug\panels\user\{UserDataNormalizer, UserIdentityRenderer};
use yii\web\View;

/**
 * @var array<int, array{attribute: string, label: string}>|null $attributes
 * @var array<string, string> $identity
 * @var View $this
 */

echo UserIdentityRenderer::render(UserDataNormalizer::fromIdentity($identity, $attributes));
