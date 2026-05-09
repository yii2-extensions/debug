<?php

declare(strict_types=1);

use yii\debug\panels\mail\{MailCardRenderer, MailMessageNormalizer};
use yii\helpers\Url;

/**
 * @var array<string, mixed> $model
 * @var int $index
 */

echo MailCardRenderer::renderItem(
    MailMessageNormalizer::from($model),
    static fn(string $file): string => Url::to(['download-mail', 'file' => $file]),
);
