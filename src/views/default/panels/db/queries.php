<?php

declare (strict_types = 1);

use yii\data\ArrayDataProvider;
use yii\debug\DbAsset;
use yii\debug\models\search\Db;
use yii\debug\panels\DbPanel;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var ArrayDataProvider $queryDataProvider
 * @var bool $hasExplain
 * @var Db $searchModel
 * @var DbPanel $panel
 * @var int $sumDuplicates
 * @var View $this
 */
echo Html::tag('h3', $panel->getName() . ' Queries');

echo GridView::widget([
    'dataProvider' => $queryDataProvider,
    'id' => 'db-panel-detailed-queries-grid',
    'options' => ['class' => 'detail-grid-view db-panel-detailed-grid table-responsive'],
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
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => function ($data) {
                $timeInSeconds = $data['timestamp'] / 1000;
                $millisecondsDiff = (int)(($timeInSeconds - (int)$timeInSeconds) * 1000);

                return date('H:i:s.', (int)$timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'duration',
            'value' => function ($data) {
                return sprintf('%.1f ms', $data['duration']);
            },
            'options' => [
                'width' => '10%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'type',
            'value' => function ($data) {
                return Html::encode($data['type']);
            },
            'filter' => $panel->getTypes(),
        ],
        [
            'attribute' => 'duplicate',
            'label' => 'Duplicated',
            'options' => [
                'width' => '5%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'query',
            'value' => function ($data) use ($hasExplain, $panel) {
                $query = Html::tag('div', Html::encode($data['query']));

                if (!empty($data['trace'])) {
                    $query .= Html::ul($data['trace'], [
                        'class' => 'trace',
                        'item' => function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }

                if ($hasExplain && $panel::canBeExplained($data['type'])) {
                    $query .= Html::tag('p', '', ['class' => 'db-explain-text']);

                    $query .= Html::tag(
                        'div',
                        Html::a(
                            '[+] Explain',
                            ['db-explain', 'seq' => $data['seq'], 'tag' => Yii::$app->controller->summary['tag']]
                        ),
                        ['class' => 'db-explain']
                    );
                }

                return $query;
            },
            'format' => 'raw',
            'options' => [
                'width' => '60%',
            ],
        ]
    ],
]);

if ($hasExplain) {
    DbAsset::register($this);

    echo Html::tag(
        'div',
        Html::a('[+] Explain all', 'javascript:;'),
        ['class' => 'db-explain-all']
    );
}
