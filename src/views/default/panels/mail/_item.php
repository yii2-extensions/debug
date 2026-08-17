<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\Mail\{MailCardRenderer, MailMessage};
use yii\helpers\Url;

/**
 * @var int $index Zero-based index of the mail message.
 * @var MailMessage $model Captured mail message.
 */
?>
<?= MailCardRenderer::renderItem(
    $model,
    static fn(string $file): string => Url::to(['download-mail', 'file' => $file]),
);
