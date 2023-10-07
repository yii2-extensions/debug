<?php

declare (strict_types=1);

use yii\debug\panels\EventPanel;

/**
 * @var EventPanel $panel
 * @var int $eventCount
 */
if ($eventCount): ?>
    <div class="yii-debug-toolbar__block">
        <a href="<?= $panel->getUrl() ?>">Events <span class="yii-debug-toolbar__label"><?= $eventCount ?></span></a>
    </div>
<?php endif ?>
