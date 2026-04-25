<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var yii\debug\panels\UserPanel $panel */
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>">
        <?php if (!isset($panel->data['id'])): ?>
            <span class="yii-debug-toolbar-label">Guest</span>
        <?php else: ?>
            <?php if ($panel->getUser()->isGuest || $panel->userSwitch->isMainUser()): ?>
                <?= Html::encode($panel->getName()) ?> <span
                    class="yii-debug-toolbar-label yii-debug-toolbar-label-info"><?= $panel->data['id'] ?></span>
            <?php else: ?>
                <?= Html::encode($panel->getName()) ?> switching <span
                    class="yii-debug-toolbar-label yii-debug-toolbar-label-warning"><?= $panel->data['id'] ?></span>
            <?php endif; ?>
            <?php if ($panel->canSwitchUser()): ?>
                <span class="yii-debug-toolbar-switch-icon yii-debug-toolbar-userswitch"
                      id="yii-debug-toolbar-switch-users">
            </span>
            <?php endif; ?>
        <?php endif; ?>
    </a>
</div>
