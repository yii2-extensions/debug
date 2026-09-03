<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\LogSearch;
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogCounts, LogRow};
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

$levelUrl = static function (int $level) use ($panel): string {
    $queryParams = [];

    foreach (Yii::$app->getRequest()->getQueryParams() as $name => $value) {
        if (is_string($name)) {
            $queryParams[$name] = $value;
        }
    }

    $queryParams['Log'] = ['level' => $level];

    unset($queryParams['page']);

    return $panel->getUrl($queryParams);
};

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
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-danger')
        ->href($levelUrl(Logger::LEVEL_ERROR))
        ->addAriaAttribute('label', "{$counts->errors} errors; filter log messages by error level")
        ->title('Show only error log messages')
        ->html(
            Strong::tag()->content((string) $counts->errors),
            ' errors',
        );
}

if ($counts->hasWarnings()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-warn')
        ->href($levelUrl(Logger::LEVEL_WARNING))
        ->addAriaAttribute('label', "{$counts->warnings} warnings; filter log messages by warning level")
        ->title('Show only warning log messages')
        ->html(
            Strong::tag()->content((string) $counts->warnings),
            ' warnings',
        );
}

if ($counts->hasInfo()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-info')
        ->href($levelUrl(Logger::LEVEL_INFO))
        ->addAriaAttribute('label', "{$counts->info} info; filter log messages by info level")
        ->title('Show only info log messages')
        ->html(
            Strong::tag()->content((string) $counts->info),
            ' info',
        );
}

if ($counts->hasTrace()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-trace')
        ->href($levelUrl(Logger::LEVEL_TRACE))
        ->addAriaAttribute('label', "{$counts->trace} trace; filter log messages by trace level")
        ->title('Show only trace log messages')
        ->html(
            Strong::tag()->content((string) $counts->trace),
            ' trace',
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
                    $panel->getTraceLine(...),
                ),
                'format' => 'raw',
                'options' => ['width' => '50%'],
            ],
        ],
    ],
);
