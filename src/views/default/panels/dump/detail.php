<?php

declare (strict_types=1);

use yii\helpers\Html;
use yii\grid\GridView;
use yii\debug\panels\DumpPanel;
use yii\debug\models\search\Log;
use yii\data\ArrayDataProvider;

/**
 * @var ArrayDataProvider $dataProvider
 * @var DumpPanel $panel
 * @var Log $searchModel
 */
?>
<h1>Dump</h1>
<?php

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'id' => 'dump-panel-detailed-grid',
    'options' => ['class' => 'detail-grid-view table-responsive'],
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        'category',
        [
            'attribute' => 'message',
            'value' => static function ($data) use ($panel) {
                $message = $data['message'];

                if (!empty($data['trace'])) {
                    $message .= Html::ul($data['trace'], [
                        'class' => 'trace',
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
]);
