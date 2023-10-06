<?php

declare (strict_types = 1);

use yii\debug\panels\ConfigPanel;

/**
 * @var ConfigPanel $panel
 */
?>
<div class="yii-debug-toolbar__block">
    <a href="<?= $panel->getUrl() ?>">
        <span class="yii-debug-toolbar__label"><?= $panel->data['application']['yii'] ?></span>
        PHP
        <span class="yii-debug-toolbar__label"><?= $panel->data['php']['version'] ?></span>
    </a>
</div>
