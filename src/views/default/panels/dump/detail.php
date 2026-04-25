<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\debug\panels\DumpPanel $panel */
/** @var yii\debug\models\search\Log $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
?>
    <h1 class="yii-debug-sr-only">Dump</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $dataProvider->getTotalCount() ?></strong> dumps captured</span>
    </header>
<?php

echo GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $dataProvider,
    'id' => 'dump-panel-detailed-grid',
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        'category',
        [
            'attribute' => 'message',
            'value' => function ($data) use ($panel) {
                $message = $data['message'];

                if (!empty($data['trace'])) {
                    $message .= Html::ul($data['trace'], [
                        'class' => 'yii-debug-trace',
                        'item' => function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }

                return $message;
            },
            'format' => 'raw',
            'options' => [
                'width' => '80%',
            ],
        ],
    ],
]));
