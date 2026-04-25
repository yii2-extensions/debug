<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\debug\models\router\CurrentRoute $currentRoute */
/** @var yii\debug\models\router\RouterRules $routerRules */
/** @var yii\debug\models\router\ActionRoutes $actionRoutes */

$items = [
    'nav' => [],
    'content' => [],
];

$items['nav'][] = 'Current Route';
$items['content'][] = $this->render('current', ['currentRoute' => $currentRoute]);

$items['nav'][] = 'Router Rules';
$items['content'][] = $this->render('rules', ['routerRules' => $routerRules]);

$items['nav'][] = 'Action Routes';
$items['content'][] = $this->render('actions', ['actionRoutes' => $actionRoutes]);

?>
<h1>Router</h1>

<ul class="yii-debug-tabs">
    <?php
    foreach ($items['nav'] as $k => $item) {
        echo Html::tag(
            'li',
            Html::a($item, '#r-tab-' . $k, [
                'class' => $k === 0 ? 'yii-debug-tab__link is-active' : 'yii-debug-tab__link',
                'data-yii-debug-toggle' => 'tab',
                'role' => 'tab',
                'aria-controls' => 'r-tab-' . $k,
                'aria-selected' => $k === 0 ? 'true' : 'false',
            ]),
            [
                'class' => 'yii-debug-tab',
            ],
        );
    }
?>
    <li class="yii-debug-tab">
        <span class="yii-debug-tab__link yii-debug-tab__link--badge">
            <span class="yii-debug-badge yii-debug-badge--<?= $routerRules->prettyUrl ? 'success' : 'muted' ?>">
                Pretty URL <?= $routerRules->prettyUrl ? 'Enabled' : 'Disabled' ?>
            </span>
        </span>
    </li>
    <li class="yii-debug-tab">
        <span class="yii-debug-tab__link yii-debug-tab__link--badge">
            <span class="yii-debug-badge yii-debug-badge--<?= $routerRules->strictParsing ? 'success' : 'muted' ?>">
                Strict Parsing <?= $routerRules->strictParsing ? 'Enabled' : 'Disabled' ?>
            </span>
        </span>
    </li>
    <?php if ($routerRules->suffix): ?>
        <li class="yii-debug-tab">
            <span class="yii-debug-tab__link yii-debug-tab__link--badge">
                <span class="yii-debug-badge yii-debug-badge--warning">
                    Global Suffix: <?= $routerRules->suffix ?>
                </span>
            </span>
        </li>
    <?php endif; ?>
</ul>
<div class="yii-debug-tab-content">
    <?php
foreach ($items['content'] as $k => $item) {
    echo Html::tag('div', $item, [
        'class' => $k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel',
        'id' => 'r-tab-' . $k,
    ]);
}
?>
</div>
