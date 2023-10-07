<?php

declare (strict_types=1);

use yii\debug\panels\MailPanel;

/**
 * @var int $mailCount
 * @var MailPanel $panel
 */
if ($mailCount): ?>
    <div class="yii-debug-toolbar__block">
        <a href="<?= $panel->getUrl() ?>">Mail <span class="yii-debug-toolbar__label"><?= $mailCount ?></span></a>
    </div>
<?php endif ?>
