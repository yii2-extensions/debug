<?php

declare(strict_types=1);

use yii\debug\panels\RequestPanel;
use yii\helpers\Html;
use yii\web\Response;

/** @var RequestPanel $panel */

$rawStatus = is_array($panel->data) ? ($panel->data['statusCode'] ?? null) : null;
$statusCode = is_int($rawStatus) ? $rawStatus : (is_numeric($rawStatus) ? (int) $rawStatus : 200);

if ($statusCode >= 200 && $statusCode < 300) {
    $class = 'yii-debug-toolbar-label-success';
} elseif ($statusCode >= 300 && $statusCode < 400) {
    $class = 'yii-debug-toolbar-label-info';
} else {
    $class = 'yii-debug-toolbar-label-important';
}

$httpStatusText = Response::$httpStatuses[$statusCode] ?? '';
$statusText = Html::encode(is_string($httpStatusText) ? $httpStatusText : '');
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="Status code: <?= $statusCode ?> <?= $statusText ?>">Status <span
            class="yii-debug-toolbar-label <?= $class ?>"><?= $statusCode ?></span></a>
</div>
