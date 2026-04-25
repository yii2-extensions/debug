<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\grid\GridView;

/** @var yii\debug\panels\EventPanel $panel */
/** @var yii\debug\models\search\Event $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
?>
<h1 class="yii-debug-sr-only">Events</h1>
<header class="yii-debug-grid-summary">
    <span><strong><?= $dataProvider->getTotalCount() ?></strong> events captured</span>
</header>
<?= GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $dataProvider,
    'id' => 'log-panel-detailed-event',
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        [
            'attribute' => 'time',
            'value' => function ($data) {
                $timeInSeconds = (int) $data['time'];
                $millisecondsDiff = (int) (($data['time'] - $timeInSeconds) * 1000);
                return date('H:i:s.', $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        [
            'attribute' => 'name',
        ],
        [
            'attribute' => 'class',
        ],
        [
            'header' => 'Sender',
            'attribute' => 'senderClass',
            'value' => function ($data) {
                return $data['senderClass'];
            },
        ],
        [
            'header' => 'Static',
            'attribute' => 'isStatic',
            'format' => 'boolean',
        ],
    ],
])); ?>
