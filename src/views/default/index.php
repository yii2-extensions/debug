<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\Debug;
use yii\debug\Panel;
use yii\debug\panels\DbPanel;
use yii\debug\widgets\FilterBanner;
use yii\debug\widgets\history\{HistoryRow, HistoryRowRenderer, HistorySummary};
use yii\grid\GridView;
use yii\grid\SerialColumn;
use yii\web\View;

/**
 * @var ArrayDataProvider $dataProvider
 * @var string $debugTheme
 * @var array<int|string, mixed> $manifest
 * @var Debug $searchModel
 * @var Panel[] $panels
 * @var string $themeIconMoon
 * @var string $themeIconSun
 * @var View $this
 */

$this->title = 'Yii Debugger';

// `cursor` query param lets a panel-view's "History" link preserve the active tag — the inline JS reads
// `data-yii-debug-cursor-init` and lands the cursor on that row instead of snapping back to the latest capture.
$cursorInit = '';
$rawCursor = Yii::$app->getRequest()->get('cursor');

if (is_string($rawCursor) && $rawCursor !== '') {
    $cursorInit = $rawCursor;
}

// Layout-driven shell: the layout reads `shellMode` + `shellData` and renders the brand bar + sidebar around our
// content. We only emit the table area.
$this->params['shellMode'] = 'index';
$this->params['shellData'] = [
    'panels' => $panels,
    'manifest' => $manifest,
    'debugTheme' => $debugTheme,
    'themeIconSun' => $themeIconSun,
    'themeIconMoon' => $themeIconMoon,
    'cursorInit' => $cursorInit,
];

$summary = HistorySummary::fromManifest($manifest);

$dbPanel = $panels['db'] ?? null;
$mailPanel = $panels['mail'] ?? null;
?>
<?= HistoryRowRenderer::renderSummary($summary) ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>

<?php if ($summary->totalRequests === 0): ?>
    <div class="yii-debug-empty-state">
        <h2>No debug data captured yet</h2>
        <p>Browse the host application to populate this view. Each request is captured automatically while <code>YII_DEBUG</code> is enabled.</p>
    </div>
<?php else: ?>
    <?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'rowOptions' => static fn(mixed $model): array => HistoryRowRenderer::buildRowOptions(
                HistoryRow::fromMixed($model),
                $searchModel,
            ),
            'columns' => array_filter(
                [
                    [
                        'class' => SerialColumn::class,
                        'headerOptions' => ['class' => 'yii-debug-col-num'],
                        'contentOptions' => ['class' => 'yii-debug-col-num'],
                        'filterOptions' => ['class' => 'yii-debug-col-num'],
                    ],
                    [
                        'attribute' => 'tag',
                        'label' => 'ID',
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderTagCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'format' => 'raw',
                        'headerOptions' => ['class' => 'yii-debug-col-id'],
                        'contentOptions' => ['class' => 'yii-debug-col-id'],
                    ],
                    [
                        'attribute' => 'time',
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderTimeCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'format' => 'raw',
                    ],
                    [
                        'attribute' => 'processingTime',
                        'label' => 'Duration',
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderDurationCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'format' => 'raw',
                    ],
                    [
                        'attribute' => 'peakMemory',
                        'label' => 'Memory',
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderMemoryCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'format' => 'raw',
                    ],
                    'ip',
                    $dbPanel instanceof DbPanel ? [
                        'attribute' => 'sqlCount',
                        'label' => 'Query',
                        'headerOptions' => ['class' => 'yii-debug-col-num'],
                        'contentOptions' => ['class' => 'yii-debug-col-num'],
                        'filterOptions' => ['class' => 'yii-debug-col-num'],
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderSqlCountCell(
                            HistoryRow::fromMixed($data),
                            $dbPanel,
                        ),
                        'format' => 'raw',
                    ] : null,
                    $mailPanel !== null ? [
                        'attribute' => 'mailCount',
                        'label' => 'Mail',
                        'headerOptions' => ['class' => 'yii-debug-col-num'],
                        'contentOptions' => ['class' => 'yii-debug-col-num'],
                        'filterOptions' => ['class' => 'yii-debug-col-num'],
                    ] : null,
                    [
                        'attribute' => 'method',
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
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderAjaxCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'filter' => ['No', 'Yes'],
                    ],
                    [
                        'attribute' => 'url',
                        'label' => 'URL',
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderUrlCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'format' => 'raw',
                    ],
                    [
                        'attribute' => 'statusCode',
                        'value' => static fn(mixed $data): string => HistoryRowRenderer::renderStatusCell(
                            HistoryRow::fromMixed($data),
                        ),
                        'format' => 'raw',
                        'filter' => $summary->statusCodeFilter,
                        'label' => 'Status',
                    ],
                ],
            ),
        ],
    ) ?>
<?php endif; ?>
