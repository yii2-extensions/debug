<?php

declare (strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\models\search\Event;
use yii\debug\panels\EventPanel;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider
 * @var Event $searchModel
 * @var EventPanel $panel
 */
?>
<h1>Events</h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'id' => 'log-panel-detailed-event',
    'options' => ['class' => 'detail-grid-view table-responsive'],
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'pager' => [
        'linkContainerOptions' => [
            'class' => 'page-item'
        ],
        'linkOptions' => [
            'class' => 'page-link'
        ],
        'disabledListItemSubTagOptions' => [
            'tag' => 'a',
            'href' => 'javascript:;',
            'tabindex' => '-1',
            'class' => 'page-link'
        ]
    ],
    'columns' => [
        [
            'attribute' => 'time',
            'value' => static function ($data): string {
                $timeInSeconds = (int) floor($data['time']);
                $millisecondsDiff = (($data['time'] - $timeInSeconds) * 1000);

                return date('H:i:s.', $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'name',
            'headerOptions' => [
                'class' => 'sort-numerical'
            ],
        ],
        [
            'attribute' => 'class',
        ],
        [
            'header' => 'Sender',
            'attribute' => 'senderClass',
            'value' => static function ($data): string {
                return $data['senderClass'];
            },
        ],
        [
            'header' => 'Static',
            'attribute' => 'isStatic',
            'format' => 'boolean',
        ],
    ],
]);
