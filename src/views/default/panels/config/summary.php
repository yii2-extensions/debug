<?php

declare(strict_types=1);

/** @var yii\debug\panels\ConfigPanel $panel */
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>">
        <span class="yii-debug-toolbar-label"><?= $panel->data['application']['yii'] ?></span>
        PHP
        <span class="yii-debug-toolbar-label"><?= $panel->data['php']['version'] ?></span>
    </a>
</div>
