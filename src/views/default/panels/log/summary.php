<?php

declare(strict_types=1);

use yii\debug\panels\LogPanel;
use yii\log\{Logger, Target};

/**
 * @var array{messages?: array<int, array<int, mixed>>} $data
 * @var LogPanel $panel
 */

$messages = $data['messages'] ?? [];

$errorCount = count(Target::filterMessages($messages, Logger::LEVEL_ERROR));
$warningCount = count(Target::filterMessages($messages, Logger::LEVEL_WARNING));

$allTitle = Yii::$app->i18n->format(
    'Logged {n,plural,=1{1 message} other{# messages}}',
    ['n' => count($messages)],
    'en-US',
);
$errorsTitle = $errorCount > 0
    ? Yii::$app->i18n->format('{n,plural,=1{1 error} other{# errors}}', ['n' => $errorCount], 'en-US')
    : '';
$warningsTitle = $warningCount > 0
    ? Yii::$app->i18n->format('{n,plural,=1{1 warning} other{# warnings}}', ['n' => $warningCount], 'en-US')
    : '';

$titles = array_filter([$allTitle, $errorsTitle, $warningsTitle], static fn(string $title): bool => $title !== '');
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="<?= implode(',&nbsp;', $titles) ?>">Log
        <span class="yii-debug-toolbar-label"><?= count($messages) ?></span>
    </a>
    <?php if ($errorCount > 0): ?>
        <a href="<?= $panel->getUrl(['Log[level]' => Logger::LEVEL_ERROR]) ?>" title="<?= $errorsTitle ?>">
            <span class="yii-debug-toolbar-label yii-debug-toolbar-label-important"><?= $errorCount ?></span>
        </a>
    <?php endif; ?>
    <?php if ($warningCount > 0): ?>
        <a href="<?= $panel->getUrl(['Log[level]' => Logger::LEVEL_WARNING]) ?>" title="<?= $warningsTitle ?>">
            <span class="yii-debug-toolbar-label yii-debug-toolbar-label-warning"><?= $warningCount ?></span>
        </a>
    <?php endif; ?>
</div>
