<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var yii\debug\panels\UserPanel $panel */

$encodedName = Html::encode($panel->getName());
?>

<h1 class="yii-debug-sr-only"><?= $encodedName ?></h1>

<?php
$panelData = is_array($panel->data) ? $panel->data : [];
$identity = $panelData['identity'] ?? null;
if ($identity !== null) {
    $items = [
        'nav' => [$encodedName],
        'content' => [
            $this->render('_identity', [
                'identity' => $identity,
                'attributes' => $panelData['attributes'] ?? null,
            ]),
        ],
    ];
    if (($panelData['rolesProvider'] ?? null) !== null || ($panelData['permissionsProvider'] ?? null) !== null) {
        $items['nav'][] = 'Roles and Permissions';
        $items['content'][] = $this->render('roles', ['panel' => $panel]);
    }

    if ($panel->canSwitchUser()) {
        $items['nav'][] = "Switch {$encodedName}";
        $items['content'][] = $this->render('switch', ['panel' => $panel]);
    }

    ?>
    <ul class="yii-debug-tabs">
        <?php
        foreach ($items['nav'] as $k => $item) {
            echo Html::tag(
                'li',
                Html::a($item, '#u-tab-' . $k, [
                    'class' => $k === 0 ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link',
                    'data-yii-debug-toggle' => 'tab',
                    'role' => 'tab',
                    'aria-controls' => 'u-tab-' . $k,
                    'aria-selected' => $k === 0 ? 'true' : 'false',
                ]),
                [
                    'class' => 'yii-debug-tab',
                ],
            );
        }
    ?>
    </ul>
    <div class="yii-debug-tab-content">
        <?php
    foreach ($items['content'] as $k => $item) {
        echo Html::tag('div', $item, [
            'class' => $k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel',
            'id' => 'u-tab-' . $k,
        ]);
    }
    ?>
    </div>
    <?php
} else {
    echo 'Is guest.';
} ?>
