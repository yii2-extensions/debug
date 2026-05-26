<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\QueueSearch;
use yii\debug\panels\queue\{JobRecordNormalizer, QueueCardRenderer, QueueGridRenderer, QueueSummaryNormalizer};
use yii\debug\panels\QueuePanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Url;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var QueuePanel $panel Panel providing the detail content.
 * @var QueueSearch $searchModel Search model for filtering the queue grid.
 */
$summary = QueueSummaryNormalizer::fromPanelData($panel->data);

$totalRecords = $summary->totalEvents();
$visibleRecords = $dataProvider->getTotalCount();

$asyncHint = QueueCardRenderer::renderAsyncHint($summary);

$componentIds = $summary->componentIds();

$driverNames = [];

foreach ($summary->records as $record) {
    if ($record->driverName !== '' && !in_array($record->driverName, $driverNames, true)) {
        $driverNames[] = $record->driverName;
    }
}

$eventTypeOptions = [
    '' => 'All',
    'push' => 'Queued (push)',
    'exec' => 'Done (exec)',
    'error' => 'Failed (error)',
];

$componentOptions = ['' => 'All'] + array_combine($componentIds, $componentIds);
$driverOptions = ['' => 'All'] + array_combine($driverNames, $driverNames);

$tag = $panel->tag;

$jobUrlBuilder = static fn(int $seq): string => Url::to(['queue-job', 'seq' => $seq, 'tag' => $tag]);
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Queue') ?>
<?php if ($totalRecords === 0): ?>
    <?= Div::tag()
        ->class('yii-debug-empty-state')
        ->html(
            H2::tag()
                ->content('No jobs queued in this request'),
            P::tag()
                ->content(
                    'This request did not push any jobs through a configured queue component, so the inventory is empty.',
                ),
            P::tag()
                ->html(
                    'The Queue panel listens for ',
                    Code::tag()->content('afterPush'),
                    ', ',
                    Code::tag()->content('afterExec'),
                    ' and ',
                    Code::tag()->content('afterError'),
                    ' events emitted by any class extending ',
                    Code::tag()->content('yii\\queue\\Queue'),
                    ' (the abstract base from ',
                    Code::tag()->content('yiisoft/yii2-queue'),
                    '). Configure a queue component (sync, db, redis, ...) and call ',
                    Code::tag()->content('$queue->push($job)'),
                    ' to populate this view.',
                ),
        ) ?>
    <?php return; ?>
<?php endif; ?>
<?php
$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) $visibleRecords),
            ' of ',
            Strong::tag()->content((string) $totalRecords),
            ' events',
        ),
    Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·'),
    Span::tag()
        ->html(
            Strong::tag()
                ->content((string) $summary->totalPushed()),
            ' pushed',
        ),
];

if ($summary->totalExecuted() > 0) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->html(
            Strong::tag()
                ->content((string) $summary->totalExecuted()),
            ' executed',
        );
}

if ($summary->hasErrors()) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-stat-danger')
        ->html(
            Strong::tag()
                ->content((string) $summary->totalErrors()),
            ' failed',
        );
}

$summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>

    <?php if ($asyncHint !== null): ?>
        <?= $asyncHint ?>
    <?php endif; ?>

    <?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
    <?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $dataProvider,
            'id' => 'queue-panel-events-grid',
            'options' => ['class' => 'yii-debug-grid yii-debug-grid-queue'],
            'filterModel' => $searchModel,
            'filterUrl' => $panel->getUrl(),
            'rowOptions' => static fn(array $model, int $key): array => [
                'class' => 'yii-debug-row-link',
                'data-href' => $jobUrlBuilder($key),
            ],
            'columns' => [
                [
                    'attribute' => 'jobId',
                    'label' => 'ID',
                    'format' => 'raw',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderIdCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical yii-debug-col-queue-id'],
                    'contentOptions' => ['class' => 'yii-debug-col-queue-id'],
                    'filterInputOptions' => ['class' => 'yii-debug-input yii-debug-col-queue-id-input'],
                ],
                [
                    'attribute' => 'eventType',
                    'label' => 'Status',
                    'format' => 'raw',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderStatusCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'filter' => $eventTypeOptions,
                    'contentOptions' => ['class' => 'yii-debug-cell-pill'],
                ],
                [
                    'attribute' => 'driverName',
                    'label' => 'Driver',
                    'format' => 'raw',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderDriverCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'filter' => $driverOptions,
                    'contentOptions' => ['class' => 'yii-debug-cell-pill'],
                ],
                [
                    'attribute' => 'componentId',
                    'label' => 'Component',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderComponentCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'filter' => $componentOptions,
                    'contentOptions' => ['class' => 'yii-debug-cell-nowrap'],
                ],
                [
                    'attribute' => 'jobClass',
                    'label' => 'Job',
                    'format' => 'raw',
                    'value' => static fn(mixed $data, int $key) => QueueGridRenderer::renderJobCell(
                        JobRecordNormalizer::from($data),
                        $jobUrlBuilder($key),
                    ),
                ],
                [
                    'attribute' => 'time',
                    'label' => 'Time',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderTimeCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical'],
                    'contentOptions' => ['class' => 'yii-debug-cell-nowrap yii-debug-cell-mono'],
                ],
                [
                    'attribute' => 'duration',
                    'label' => 'Duration',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderDurationCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical'],
                    'contentOptions' => ['class' => 'yii-debug-cell-nowrap yii-debug-cell-numeric'],
                ],
                [
                    'label' => 'TTR',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderTtrCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical'],
                    'contentOptions' => ['class' => 'yii-debug-cell-nowrap yii-debug-cell-numeric'],
                ],
                [
                    'label' => 'Attempt',
                    'value' => static fn(mixed $data): string => QueueGridRenderer::renderAttemptCell(
                        JobRecordNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical'],
                    'contentOptions' => ['class' => 'yii-debug-cell-nowrap yii-debug-cell-numeric'],
                ],
            ],
        ],
    );
