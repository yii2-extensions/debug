<?php

declare(strict_types=1);

use yii\debug\DbAsset;
use yii\debug\GridViewConfig;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/** @var yii\debug\panels\DbPanel $panel */
/** @var yii\debug\models\search\Db $searchModel */
/** @var yii\data\ArrayDataProvider $queryDataProvider */
/** @var bool $hasExplain */
/** @var int $sumDuplicates */
/** @var View $this */

echo Html::tag('h3', $panel->getName() . ' Queries');

echo GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $queryDataProvider,
    'id' => 'db-panel-detailed-queries-grid',
    'options' => ['class' => 'yii-debug-grid yii-debug-grid-db'],
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
            'options' => ['width' => '5%'],
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        [
            'attribute' => 'query',
            'value' => function ($data) use ($hasExplain, $panel) {
                $query = Html::tag('div', Html::encode($data['query']));

                if (!empty($data['trace'])) {
                    $query .= Html::ul($data['trace'], [
                        'class' => 'yii-debug-trace',
                        'item' => function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }

                if ($hasExplain && $panel::canBeExplained($data['type'])) {
                    $query .= Html::tag('p', '', ['class' => 'yii-debug-db-explain-text']);

                    $query .= Html::tag(
                        'div',
                        Html::a(
                            '[+] Explain',
                            ['db-explain', 'seq' => $data['seq'], 'tag' => Yii::$app->controller->summary['tag']],
                        ),
                        ['class' => 'yii-debug-db-explain'],
                    );
                }

                return $query;
            },
            'format' => 'raw',
            'options' => ['width' => '60%'],
        ],
    ],
]));

if ($hasExplain) {
    DbAsset::register($this);

    echo Html::tag(
        'div',
        Html::a('[+] Explain all', 'javascript:;'),
        ['class' => 'yii-debug-db-explain-all'],
    );
}
