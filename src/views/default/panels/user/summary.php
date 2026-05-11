<?php

declare(strict_types=1);

use yii\debug\panels\UserPanel;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var UserPanel $panel
 * @var View $this
 */

$data = is_array($panel->data) ? $panel->data : [];
$id = $data['id'] ?? null;
$idLabel = is_scalar($id) ? (string) $id : '';
$user = $panel->getUser();
$isGuest = $user === null || $user->isGuest;
$isMainUser = $panel->userSwitch === null || $panel->userSwitch->isMainUser();
?>
<div class="yii-debug-toolbar-block">
    <a href="<?= $panel->getUrl() ?>">
        <?php if ($id === null): ?>
            <span class="yii-debug-toolbar-label">Guest</span>
        <?php else: ?>
            <?php if ($isGuest || $isMainUser): ?>
                <?= Html::encode($panel->getName()) ?> <span
                    class="yii-debug-toolbar-label yii-debug-toolbar-label-info"><?= Html::encode($idLabel) ?></span>
            <?php else: ?>
                <?= Html::encode($panel->getName()) ?> switching <span
                    class="yii-debug-toolbar-label yii-debug-toolbar-label-warning"><?= Html::encode($idLabel) ?></span>
            <?php endif; ?>
            <?php if ($panel->canSwitchUser()): ?>
                <span class="yii-debug-toolbar-switch-icon yii-debug-toolbar-userswitch"
                    id="yii-debug-toolbar-switch-users">
            </span>
            <?php endif; ?>
        <?php endif; ?>
    </a>
</div>
