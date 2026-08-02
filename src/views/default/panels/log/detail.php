<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\LogSearch;
use yii\debug\panels\log\{LogCellRenderer, LogCounts, LogRow};
use yii\debug\panels\LogPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\log\Logger;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var LogPanel $panel Panel providing the detail content.
 * @var LogSearch $searchModel Search model for filtering the log grid.
 */
$counts = LogCounts::fromRows($panel->getMessages());

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) $counts->total),
            ' messages',
        ),
];

if ($counts->hasErrors()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-stat-danger')
        ->html(
            Strong::tag()->content((string) $counts->errors),
            ' errors',
        );
}

if ($counts->hasWarnings()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-stat-warn')
        ->html(
            Strong::tag()->content((string) $counts->warnings),
            ' warnings',
        );
}

if ($counts->hasInfo()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->html(
            Strong::tag()->content((string) $counts->info),
            ' info',
        );
}

$summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Log Messages') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'log-panel-detailed-grid',
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-log'],
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'rowOptions' => static fn(LogRow $model): array => LogCellRenderer::buildRowOptions($model),
        'columns' => [
            [
                'attribute' => 'id',
                'label' => '#',
                'contentOptions' => ['class' => 'yii-debug-nowrap'],
            ],
            [
                'attribute' => 'time',
                'value' => static fn(LogRow $data): string => LogCellRenderer::renderTimeCell($data),
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-nowrap'],
            ],
            [
                'attribute' => 'timeSincePrevious',
                'value' => static fn(LogRow $data): string => LogCellRenderer::renderTimeSincePreviousCell($data),
                'format' => 'raw',
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'level',
                'value' => static fn(LogRow $data): string => LogCellRenderer::renderLevelCell($data),
                'format' => 'raw',
                'filter' => [
                    Logger::LEVEL_TRACE => ' Trace ',
                    Logger::LEVEL_INFO => ' Info ',
                    Logger::LEVEL_WARNING => ' Warning ',
                    Logger::LEVEL_ERROR => ' Error ',
                ],
            ],
            [
                'attribute' => 'category',
                'value' => static fn(LogRow $data): string => LogCellRenderer::renderCategoryCell($data),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-cell-fqcn'],
            ],
            [
                'attribute' => 'message',
                'value' => static fn(LogRow $data): string => LogCellRenderer::renderMessageCell(
                    $data,
                    $panel,
                ),
                'format' => 'raw',
                'options' => ['width' => '50%'],
            ],
        ],
    ],
);
