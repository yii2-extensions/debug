<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\debug\panels\DumpPanel $panel */
/** @var yii\debug\models\search\Log $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
?>
    <h1 class="yii-debug-sr-only">Dump</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $dataProvider->getTotalCount() ?></strong> dumps captured</span>
        <?= GridViewConfig::pageSizeSelectorHtml() ?>
    </header>
    <?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
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
            'value' => static function (array $data) use ($panel): string {
                $message = is_string($data['message'] ?? null) ? $data['message'] : '';

                $trace = $data['trace'] ?? null;
                if (is_array($trace) && $trace !== []) {
                    $message .= Html::ul($trace, [
                        'class' => 'yii-debug-trace',
                        'item' => static function ($traceItem) use ($panel): string {
                            return '<li>' . $panel->getTraceLine(is_array($traceItem) ? $traceItem : []) . '</li>';
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
