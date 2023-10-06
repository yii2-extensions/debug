<?php

declare (strict_types = 1);

use yii\debug\panels\RouterPanel;
use yii\helpers\Html;

/**
 * @var RouterPanel $panel
 */
?>
<div class="yii-debug-toolbar__block">
    <a href="<?= $panel->getUrl() ?>" title="Action: <?= Html::encode($panel->data['action']) ?>">
        Route
        <span class="yii-debug-toolbar__label"><?= Html::encode($panel->data['route']) ?></span>
    </a>
</div>
