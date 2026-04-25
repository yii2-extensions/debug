<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\debug\panels\ProfilingPanel $panel */
/** @var yii\debug\models\search\Profile $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var int $time */
/** @var int $memory */
?>
    <h1 class="yii-debug-sr-only">Performance Profiling</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $time ?></strong> total</span>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span><strong><?= $memory ?></strong> peak</span>
        <span class="yii-debug-grid-summary-sep">·</span>
        <?= Html::a('Open timeline', [
            '/' . $panel->module->getUniqueId() . '/default/view',
            'panel' => 'timeline',
            'tag' => $panel->tag,
        ]) ?>
    </header>
<?php
echo GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $dataProvider,
    'id' => 'profile-panel-detailed-grid',
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        [
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => function ($data) {
                $timeInSeconds = $data['timestamp'] / 1000;
                $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

                return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        [
            'attribute' => 'duration',
            'value' => function ($data) {
                return sprintf('%.1f ms', $data['duration']);
            },
            'options' => ['width' => '10%'],
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        'category',
        [
            'attribute' => 'info',
            'value' => function ($data) {
                return str_repeat('<span class="yii-debug-indent">→</span>', $data['level']) . Html::encode($data['info']);
            },
            'format' => 'html',
            'options' => ['width' => '60%'],
        ],
    ],
]));
