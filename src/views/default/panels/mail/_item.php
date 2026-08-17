<?php

declare(strict_types=1);

use yii\debug\Module;
use PHPForge\Debug\Panel\Mail\{MailCardRenderer, MailMessage};
use yii\helpers\Url;

/**
 * @var int $index Zero-based index of the mail message.
 * @var MailMessage $model Captured mail message.
 */
?>
<?= MailCardRenderer::renderItem(
    $model,
    static fn(string $file): string => Url::to(Module::route('download-mail', ['file' => $file])),
);
