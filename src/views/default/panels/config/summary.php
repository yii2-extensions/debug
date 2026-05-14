<?php

declare(strict_types=1);

/** @var yii\debug\panels\ConfigPanel $panel */

use UIAwesome\Html\Helper\Encode;

$data = is_array($panel->data) ? $panel->data : [];
$application = is_array($data['application'] ?? null) ? $data['application'] : [];
$php = is_array($data['php'] ?? null) ? $data['php'] : [];
$yiiVersion = is_string($application['yii'] ?? null) ? $application['yii'] : '';
$phpVersion = is_string($php['version'] ?? null) ? $php['version'] : '';
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>">
        <span class="yii-debug-toolbar-label"><?= Encode::content($yiiVersion) ?></span>
        PHP
        <span class="yii-debug-toolbar-label"><?= Encode::content($phpVersion) ?></span>
    </a>
</div>
