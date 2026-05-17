<?php

declare(strict_types=1);
/** @var yii\debug\panels\EventPanel $panel */
/** @var int $eventCount */
if ($eventCount > 0): ?>
    <div class="yii-debug-toolbar-block">
        <a href="<?= $panel->getUrl() ?>">Events <span class="yii-debug-toolbar-label"><?= $eventCount ?></span></a>
    </div>
<?php endif ?>
