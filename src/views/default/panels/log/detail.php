<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\PanelTitle;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\LogSearch;
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogCounts, LogMessage, LogRow};
use yii\debug\panels\LogPanel;
use yii\debug\widgets\{FilterBanner, GridView};
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
            LogMessage::MESSAGES_SUFFIX->value,
        ),
];

if ($counts->hasErrors()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-danger')
        ->href($levelUrl(Logger::LEVEL_ERROR))
        ->addAriaAttribute(
            'label',
            sprintf(
                LogMessage::CHIP_ARIA->value,
                $counts->errors,
                LogMessage::LEVEL_ERRORS->value,
                LogMessage::LEVEL_ERROR->value,
            ),
        )
        ->title(sprintf(LogMessage::CHIP_TITLE->value, LogMessage::LEVEL_ERROR->value))
        ->html(
            Strong::tag()->content((string) $counts->errors),
            ' ' . LogMessage::LEVEL_ERRORS->value,
        );
}

if ($counts->hasWarnings()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-warn')
        ->href($levelUrl(Logger::LEVEL_WARNING))
        ->addAriaAttribute(
            'label',
            sprintf(
                LogMessage::CHIP_ARIA->value,
                $counts->warnings,
                LogMessage::LEVEL_WARNINGS->value,
                LogMessage::LEVEL_WARNING->value,
            ),
        )
        ->title(sprintf(LogMessage::CHIP_TITLE->value, LogMessage::LEVEL_WARNING->value))
        ->html(
            Strong::tag()->content((string) $counts->warnings),
            ' ' . LogMessage::LEVEL_WARNINGS->value,
        );
}

if ($counts->hasInfo()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-info')
        ->href($levelUrl(Logger::LEVEL_INFO))
        ->addAriaAttribute(
            'label',
            sprintf(
                LogMessage::CHIP_ARIA->value,
                $counts->info,
                LogMessage::LEVEL_INFO->value,
                LogMessage::LEVEL_INFO->value,
            ),
        )
        ->title(sprintf(LogMessage::CHIP_TITLE->value, LogMessage::LEVEL_INFO->value))
        ->html(
            Strong::tag()->content((string) $counts->info),
            ' ' . LogMessage::LEVEL_INFO->value,
        );
}

if ($counts->hasTrace()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->class('yii-debug-grid-summary-stat-trace')
        ->href($levelUrl(Logger::LEVEL_TRACE))
        ->addAriaAttribute(
            'label',
            sprintf(
                LogMessage::CHIP_ARIA->value,
                $counts->trace,
                LogMessage::LEVEL_TRACE->value,
                LogMessage::LEVEL_TRACE->value,
            ),
        )
        ->title(sprintf(LogMessage::CHIP_TITLE->value, LogMessage::LEVEL_TRACE->value))
        ->html(
            Strong::tag()->content((string) $counts->trace),
            ' ' . LogMessage::LEVEL_TRACE->value,
        );
}

$summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content(PanelTitle::LOG_MESSAGES) ?>
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
                'label' => LogMessage::NUMBER->value,
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
                    Logger::LEVEL_TRACE => ' ' . LogMessage::FILTER_TRACE->value . ' ',
                    Logger::LEVEL_INFO => ' ' . LogMessage::FILTER_INFO->value . ' ',
                    Logger::LEVEL_WARNING => ' ' . LogMessage::FILTER_WARNING->value . ' ',
                    Logger::LEVEL_ERROR => ' ' . LogMessage::FILTER_ERROR->value . ' ',
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
