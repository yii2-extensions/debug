<?php

declare(strict_types=1);
/** @var yii\debug\panels\MailPanel $panel */
/** @var int $mailCount */
if ($mailCount > 0): ?>
    <div class="yii-debug-toolbar-block">
        <a href="<?= $panel->getUrl() ?>">Mail <span class="yii-debug-toolbar-label"><?= $mailCount ?></span></a>
    </div>
<?php endif ?>
