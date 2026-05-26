<?php

declare(strict_types=1);

use yii\debug\panels\user\{UserDataNormalizer, UserIdentityRenderer};
use yii\web\View;

/**
 * @var array<int, array{attribute: string, label: string}>|null $attributes Optional identity attribute definitions.
 * @var array<string, string> $identity Identity attribute values keyed by name.
 * @var View $this View component instance.
 */
?>
<?= UserIdentityRenderer::render(UserDataNormalizer::fromIdentity($identity, $attributes));
