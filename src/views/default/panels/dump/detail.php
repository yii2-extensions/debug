<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\LogSearch;
use yii\debug\panels\dump\{DumpCardRenderer, DumpRowNormalizer};
use yii\debug\panels\DumpPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider
 * @var DumpPanel $panel
 * @var LogSearch $searchModel
 */
?>
    <h1 class="yii-debug-sr-only">Dump</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $dataProvider->getTotalCount() ?></strong> dumps captured</span>
        <?= GridViewConfig::pageSizeSelectorHtml() ?>
    </header>
    <?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?php

echo GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'dump-panel-detailed-grid',
        'filterModel' => $searchModel,
        'columns' => [
            'category',
            [
                'attribute' => 'message',
                'value' => static fn(mixed $data, mixed $key, int $index): string => DumpCardRenderer::renderMessageCell(
                    DumpRowNormalizer::from($data),
                    $panel,
                    $index,
                ),
                'format' => 'raw',
                'options' => ['width' => '80%'],
            ],
        ],
    ],
);
