<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var \yii\web\View $this */
/** @var yii\debug\panels\UserPanel $panel */

$encodedName = Html::encode($panel->getName());
?>

<h1><?= $encodedName ?></h1>

<?php
if (isset($panel->data['identity'])) {
    $items = [
        'nav' => [$encodedName],
        'content' => [
            "<h2>{$encodedName} Info</h2>" . DetailView::widget([
                'model' => $panel->data['identity'],
                'attributes' => $panel->data['attributes'],
            ]),
        ],
    ];
    if ($panel->data['rolesProvider'] || $panel->data['permissionsProvider']) {
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
                    'class' => $k === 0 ? 'yii-debug-tab__link is-active' : 'yii-debug-tab__link',
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
