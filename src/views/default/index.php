<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\DebugSearch;
use yii\debug\Panel;
use yii\debug\panels\DbPanel;
use yii\debug\storage\RequestSummary;
use yii\debug\widgets\FilterBanner;
use yii\debug\widgets\history\{HistoryRow, HistoryRowRenderer, HistoryScale, HistorySummary};
use yii\grid\{GridView, SerialColumn};
use yii\web\View;
use UIAwesome\Html\Heading\H1;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var array<string, RequestSummary> $manifest Debug data manifest, newest first.
 * @var Panel[] $panels Debug panels.
 * @var DebugSearch $searchModel Search model for filtering debug data.
 * @var View $this View component instance.
 */
$this->title = 'Yii Debugger';

$summary = HistorySummary::fromManifest($manifest);
$scale = HistoryScale::fromModels(
    array_values(array_filter($dataProvider->getModels(), static fn(mixed $row): bool => $row instanceof HistoryRow)),
);

$dbPanel = $panels['db'] ?? null;
$mailPanel = $panels['mail'] ?? null;
?>
<?= H1::tag()->class('yii-debug-sr-only')->content('Request history') ?>
<?= HistoryRowRenderer::renderSummary($summary) ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-history'],
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'rowOptions' => static fn(HistoryRow $model): array => HistoryRowRenderer::buildRowOptions(
            $model,
            $searchModel,
        ),
        'columns' => array_filter(
            [
                [
                    'class' => SerialColumn::class,
                    'headerOptions' => ['class' => 'yii-debug-col-num', 'scope' => 'col'],
                    'contentOptions' => ['class' => 'yii-debug-col-num'],
                    'filterOptions' => ['class' => 'yii-debug-col-num'],
                ],
                [
                    'attribute' => 'tag',
                    'label' => 'ID',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderTagCell($data),
                    'format' => 'raw',
                    'headerOptions' => ['class' => 'yii-debug-col-id'],
                    'contentOptions' => ['class' => 'yii-debug-col-id'],
                    'filterInputOptions' => ['class' => 'yii-debug-input yii-debug-col-id-input'],
                ],
                [
                    'attribute' => 'time',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderTimeCell($data),
                    'format' => 'raw',
                ],
                [
                    'attribute' => 'processingTime',
                    'label' => 'Duration',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderDurationCell(
                        $data,
                        $scale->maxProcessingTime,
                    ),
                    'format' => 'raw',
                ],
                [
                    'attribute' => 'peakMemory',
                    'label' => 'Memory',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderMemoryCell(
                        $data,
                        $scale->maxPeakMemory,
                    ),
                    'format' => 'raw',
                ],
                [
                    'attribute' => 'ip',
                    'headerOptions' => ['class' => 'yii-debug-col-ip'],
                    'contentOptions' => ['class' => 'yii-debug-col-ip'],
                    'filterOptions' => ['class' => 'yii-debug-col-ip'],
                ],
                $dbPanel instanceof DbPanel ? [
                    'attribute' => 'sqlCount',
                    'label' => 'Query',
                    'headerOptions' => ['class' => 'yii-debug-col-num'],
                    'contentOptions' => ['class' => 'yii-debug-col-num'],
                    'filterOptions' => ['class' => 'yii-debug-col-num'],
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderSqlCountCell(
                        $data,
                        $dbPanel,
                    ),
                    'format' => 'raw',
                ] : null,
                $mailPanel !== null ? [
                    'attribute' => 'mailCount',
                    'label' => 'Mail',
                    'headerOptions' => ['class' => 'yii-debug-col-num yii-debug-col-mail'],
                    'contentOptions' => ['class' => 'yii-debug-col-num yii-debug-col-mail'],
                    'filterOptions' => ['class' => 'yii-debug-col-num yii-debug-col-mail'],
                ] : null,
                [
                    'attribute' => 'method',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderMethodCell($data),
                    'format' => 'raw',
                    'filter' => [
                        'get' => 'GET',
                        'post' => 'POST',
                        'delete' => 'DELETE',
                        'put' => 'PUT',
                        'head' => 'HEAD',
                        'command' => 'COMMAND',
                    ],
                ],
                [
                    'attribute' => 'ajax',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderAjaxCell($data),
                    'filter' => ['No', 'Yes'],
                ],
                [
                    'attribute' => 'url',
                    'label' => 'URL',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderUrlCell($data),
                    'format' => 'raw',
                ],
                [
                    'attribute' => 'statusCode',
                    'value' => static fn(HistoryRow $data): string => HistoryRowRenderer::renderStatusCell($data),
                    'format' => 'raw',
                    'filter' => $summary->statusCodeFilter,
                    'label' => 'Status',
                ],
            ],
        ),
    ],
);
