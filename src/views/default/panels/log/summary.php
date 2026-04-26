<?php

declare(strict_types=1);

use yii\log\Logger;
use yii\log\Target;

/** @var yii\debug\panels\LogPanel $panel */
/** @var array{messages?: array<int, array<int, mixed>>} $data */

$messages = $data['messages'] ?? [];
$titles = [
    'all' => Yii::$app->i18n->format('Logged {n,plural,=1{1 message} other{# messages}}', ['n' => count($messages)], 'en-US'),
];
$errorCount = count(Target::filterMessages($messages, Logger::LEVEL_ERROR));
$warningCount = count(Target::filterMessages($messages, Logger::LEVEL_WARNING));

if ($errorCount > 0) {
    $titles['errors'] = Yii::$app->i18n->format('{n,plural,=1{1 error} other{# errors}}', ['n' => $errorCount], 'en-US');
}

if ($warningCount > 0) {
    $titles['warnings'] = Yii::$app->i18n->format('{n,plural,=1{1 warning} other{# warnings}}', ['n' => $warningCount], 'en-US');
}
?>

<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="<?= implode(',&nbsp;', $titles) ?>">Log
        <span class="yii-debug-toolbar-label"><?= count($messages) ?></span>
    </a>
    <?php if ($errorCount > 0): ?>
        <a href="<?= $panel->getUrl(['Log[level]' => Logger::LEVEL_ERROR]) ?>" title="<?= $titles['errors'] ?>">
            <span class="yii-debug-toolbar-label yii-debug-toolbar-label-important"><?= $errorCount ?></span>
        </a>
    <?php endif; ?>
    <?php if ($warningCount > 0): ?>
        <a href="<?= $panel->getUrl(['Log[level]' => Logger::LEVEL_WARNING]) ?>" title="<?= $titles['warnings'] ?>">
            <span class="yii-debug-toolbar-label yii-debug-toolbar-label-warning"><?= $warningCount ?></span>
        </a>
    <?php endif; ?>
</div>
