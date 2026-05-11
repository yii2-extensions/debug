<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\Queue;
use yii\debug\panels\queue\{JobRecordNormalizer, QueueCardRenderer, QueueGridRenderer, QueueSummaryNormalizer};
use yii\debug\panels\QueuePanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Url;

/**
 * @var ArrayDataProvider $dataProvider
 * @var Queue $searchModel
 * @var QueuePanel $panel
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
<h1 class="yii-debug-sr-only">Queue</h1>

<?php if ($totalRecords === 0): ?>
    <div class="yii-debug-empty-state">
        <h2>No jobs queued in this request</h2>
        <p>This request did not push any jobs through a configured queue component, so the inventory is empty.</p>
        <p>The Queue panel listens for <code>afterPush</code>, <code>afterExec</code> and <code>afterError</code> events emitted by any class extending <code>yii\queue\Queue</code> (the abstract base from <code>yiisoft/yii2-queue</code>). Configure a queue component (sync, db, redis, ...) and call <code>$queue-&gt;push($job)</code> to populate this view.</p>
    </div>
<?php else: ?>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $visibleRecords ?></strong> of <strong><?= $totalRecords ?></strong> events</span>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span><strong><?= $summary->totalPushed() ?></strong> pushed</span>

        <?php if ($summary->totalExecuted() > 0): ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <span><strong><?= $summary->totalExecuted() ?></strong> executed</span>
        <?php endif; ?>

        <?php if ($summary->hasErrors()): ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <span class="yii-debug-grid-summary-stat-danger"><strong><?= $summary->totalErrors() ?></strong> failed</span>
        <?php endif; ?>
        <?= GridViewConfig::pageSizeSelectorHtml() ?>
    </header>

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
    ) ?>
<?php endif;
