<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\debug\panels\RouterPanel $panel */

$data = is_array($panel->data) ? $panel->data : [];
$action = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';
$route = isset($data['route']) && is_string($data['route']) ? $data['route'] : '';
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="Action: <?= Html::encode($action) ?>">Route <span
            class="yii-debug-toolbar-label"><?= Html::encode($route) ?></span></a>
</div>
