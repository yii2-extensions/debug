<?php

declare(strict_types=1);
/** @var yii\debug\panels\MailPanel $panel */
/** @var int $mailCount */
if ($mailCount): ?>
    <div class="yii-debug-toolbar__block">
        <a href="<?= $panel->getUrl() ?>">Mail <span class="yii-debug-toolbar__label"><?= $mailCount ?></span></a>
    </div>
<?php endif ?>
