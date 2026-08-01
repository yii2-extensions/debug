<?php

declare(strict_types=1);

use yii\debug\panels\mail\{MailCardRenderer, MailMessage};
use yii\helpers\Url;

/**
 * @var int $index Zero-based index of the mail message.
 * @var array<string, mixed> $model Raw mail message data.
 */
?>
<?= MailCardRenderer::renderItem(
    MailMessage::fromMixed($model),
    static fn(string $file): string => Url::to(['download-mail', 'file' => $file]),
);
