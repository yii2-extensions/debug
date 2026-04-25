<?php

declare(strict_types=1);

/** @var yii\debug\panels\ProfilingPanel $panel */
/** @var int $time */
/** @var int $memory */
?>
<div class="yii-debug-toolbar__block">
    <a href="<?= $panel->getUrl() ?>" title="Total request processing time was <?= $time ?>">Time <span
            class="yii-debug-toolbar__label yii-debug-toolbar__label_info"><?= $time ?></span></a>
    <a href="<?= $panel->getUrl() ?>" title="Peak memory consumption">Memory <span
            class="yii-debug-toolbar__label yii-debug-toolbar__label_info"><?= $memory ?></span></a>
</div>
