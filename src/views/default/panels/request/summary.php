<?php

declare(strict_types=1);

use yii\debug\panels\RequestPanel;
use yii\helpers\Html;
use yii\web\Response;

/** @var RequestPanel $panel */

$statusCode = isset($panel->data['statusCode']) ? $panel->data['statusCode'] : null;
if ($statusCode === null) {
    $statusCode = 200;
}
if ($statusCode >= 200 && $statusCode < 300) {
    $class = 'yii-debug-toolbar-label-success';
} elseif ($statusCode >= 300 && $statusCode < 400) {
    $class = 'yii-debug-toolbar-label-info';
} else {
    $class = 'yii-debug-toolbar-label-important';
}
$statusText = Html::encode(isset(Response::$httpStatuses[$statusCode]) ? Response::$httpStatuses[$statusCode] : '');
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="Status code: <?= $statusCode ?> <?= $statusText ?>">Status <span
            class="yii-debug-toolbar-label <?= $class ?>"><?= $statusCode ?></span></a>
</div>
