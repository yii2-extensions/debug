<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\debug\panels\RouterPanel $panel */

?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="Action: <?= Html::encode($panel->data['action']) ?>">Route <span
            class="yii-debug-toolbar-label"><?= Html::encode($panel->data['route']) ?></span></a>
</div>
