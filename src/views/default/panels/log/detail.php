<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\LogSearch;
use yii\debug\panels\log\{LogCellRenderer, LogCountsNormalizer, LogRowNormalizer};
use yii\debug\panels\LogPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\log\Logger;

/**
 * @var ArrayDataProvider $dataProvider
 * @var LogSearch $searchModel
 * @var LogPanel $panel
 */

$counts = LogCountsNormalizer::fromPanelData($panel->data);
?>
<h1 class="yii-debug-sr-only">Log Messages</h1>
<header class="yii-debug-grid-summary">
    <span><strong><?= $counts->total ?></strong> messages</span>

    <?php if ($counts->hasErrors()): ?>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span class="yii-debug-grid-summary-stat-danger"><strong><?= $counts->errors ?></strong> errors</span>
    <?php endif; ?>

    <?php if ($counts->hasWarnings()): ?>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span class="yii-debug-grid-summary-stat-warn"><strong><?= $counts->warnings ?></strong> warnings</span>
    <?php endif; ?>

    <?php if ($counts->hasInfo()): ?>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span><strong><?= $counts->info ?></strong> info</span>
    <?php endif; ?>

    <?= GridViewConfig::pageSizeSelectorHtml() ?>
</header>

<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?php
echo GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'log-panel-detailed-grid',
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-log'],
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'rowOptions' => static fn(mixed $model): array => LogCellRenderer::buildRowOptions(
            LogRowNormalizer::from($model),
        ),
        'columns' => [
            [
                'attribute' => 'id',
                'label' => '#',
                'contentOptions' => ['class' => 'yii-debug-nowrap'],
            ],
            [
                'attribute' => 'time',
                'value' => static fn(mixed $data): string => LogCellRenderer::renderTimeCell(
                    LogRowNormalizer::from($data),
                ),
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-nowrap'],
            ],
            [
                'attribute' => 'time_since_previous',
                'value' => static fn(mixed $data): string => LogCellRenderer::renderTimeSincePreviousCell(
                    LogRowNormalizer::from($data),
                ),
                'format' => 'raw',
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'level',
                'value' => static fn(mixed $data): string => LogCellRenderer::renderLevelCell(
                    LogRowNormalizer::from($data),
                ),
                'filter' => [
                    Logger::LEVEL_TRACE => ' Trace ',
                    Logger::LEVEL_INFO => ' Info ',
                    Logger::LEVEL_WARNING => ' Warning ',
                    Logger::LEVEL_ERROR => ' Error ',
                ],
            ],
            'category',
            [
                'attribute' => 'message',
                'value' => static fn(mixed $data): string => LogCellRenderer::renderMessageCell(
                    LogRowNormalizer::from($data),
                    $panel
                ),
                'format' => 'raw',
                'options' => ['width' => '50%'],
            ],
        ],
    ],
);
