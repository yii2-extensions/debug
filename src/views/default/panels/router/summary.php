<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Encode;

/** @var yii\debug\panels\RouterPanel $panel */

$data = is_array($panel->data) ? $panel->data : [];
$action = $data['action'] ?? '';
$route = $data['route'] ?? '';
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>" title="Action: <?= Encode::value($action) ?>">Route <span
            class="yii-debug-toolbar-label"><?= Encode::content($route) ?></span></a>
</div>
