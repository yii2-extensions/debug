<?php

declare (strict_types=1);

use yii\helpers\Html;
use yii\web\Response;
use yii\debug\panels\RequestPanel;

/**
 * @var RequestPanel $panel
 */
$statusCode = $panel->data['statusCode'] ?? null;

if ($statusCode === null) {
    $statusCode = 200;
}

if ($statusCode >= 200 && $statusCode < 300) {
    $class = 'yii-debug-toolbar__label_success';
} elseif ($statusCode >= 300 && $statusCode < 400) {
    $class = 'yii-debug-toolbar__label_info';
} else {
    $class = 'yii-debug-toolbar__label_important';
}

$statusText = Html::encode(Response::$httpStatuses[$statusCode] ?? '');
?>
<div class="yii-debug-toolbar__block">
    <a href="<?= $panel->getUrl() ?>" title="Status code: <?= $statusCode ?> <?= $statusText ?>">
        Status
        <span class="yii-debug-toolbar__label <?= $class ?>"><?= $statusCode ?></span>
    </a>
</div>
