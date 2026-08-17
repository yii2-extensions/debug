<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use PHPForge\Debug\Helper\EmptyState;
use yii\debug\models\search\DbSearch;
use PHPForge\Debug\Panel\Db\{DbQueryRenderer, QueryRow};
use yii\debug\panels\DbPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\web\View;

/**
 * @var bool $hasExplain Whether the database driver supports EXPLAIN.
 * @var DbPanel $panel Panel providing the detail content.
 * @var ArrayDataProvider $queryDataProvider Data provider for the query GridView widget.
 * @var DbSearch $searchModel Search model for filtering the database query grid.
 * @var int $sumDuplicates Number of duplicated queries.
 * @var View $this View component instance.
 */
$rows = $panel->getRows();

$hasQueries = $rows !== [];

$totalMs = number_format(array_sum(array_column($rows, 'duration')), 3);

$tag = $panel->tag;

$explainUrlBuilder = static fn(int $seq): string => Url::to(['db-explain', 'seq' => $seq, 'tag' => $tag]);

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) count($rows)),
            ' queries',
        ),
    Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·'),
    Span::tag()
        ->html(
            Strong::tag()->content($totalMs),
            ' ms total',
        ),
];

if ($sumDuplicates > 0) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-stat-warn')
        ->html(
            Strong::tag()->content((string) $sumDuplicates),
            ' duplicated',
        );
}

if ($hasQueries) {
    $summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
}
?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?php if (!$hasQueries): ?>
    <?= EmptyState::card(
        'No database queries in this request',
        P::tag()
            ->content('This request completed without executing SQL through a Yii DB connection, so the query log is empty.'),
        P::tag()
            ->html(
                'Queries are captured from the profiling messages logged by ',
                Code::tag()->content('yii\db\Command'),
                ' — connections must keep ',
                Code::tag()->content('enableProfiling'),
                ' enabled (the default). After a redirect the queries usually belong to the previous request — open it from the History panel.',
            ),
    ) ?>
    <?php return; ?>
<?php endif; ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $queryDataProvider,
        'id' => 'db-panel-detailed-queries-grid',
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-db'],
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'columns' => [
            [
                'attribute' => 'type',
                'label' => 'Type',
                'format' => 'raw',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderTypeCell($data),
                'filter' => $panel->getTypes(),
                'options' => ['width' => '8%'],
            ],
            [
                'attribute' => 'seq',
                'label' => 'Time',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderTimeCell($data),
                'headerOptions' => ['class' => 'sort-numerical'],
                'options' => ['width' => '10%'],
            ],
            [
                'attribute' => 'duration',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderDurationCell($data),
                'options' => ['width' => '8%'],
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'rows',
                'label' => 'Rows',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderRowsCell($data),
                'options' => ['width' => '7%'],
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'duplicate',
                'label' => 'Dup',
                'options' => ['width' => '5%'],
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'query',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderQueryCell(
                    $data,
                    $panel->getTraceLine(...),
                    $hasExplain,
                    $explainUrlBuilder,
                ),
                'format' => 'raw',
                'options' => ['width' => '60%'],
            ],
        ],
    ],
) ?>
<?php if ($hasExplain): ?>
    <?= Div::tag()
        ->class('yii-debug-db-explain-all')
        ->html(
            A::tag()->href('javascript:;')->content('[+] Explain all'),
        ) ?>
<?php endif;
