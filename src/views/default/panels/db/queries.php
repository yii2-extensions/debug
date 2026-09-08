<?php

declare(strict_types=1);

use yii\debug\Module;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Phrasing\Code;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use PHPForge\Debug\Helper\EmptyState;
use yii\debug\models\search\DbSearch;
use PHPForge\Debug\Panel\Db\{
    DbMessage,
    DbQueryRenderer,
    DbSummaryRenderer,
    NPlusOneDetector,
    NPlusOneFinding,
    QueryRow,
};
use yii\debug\panels\DbPanel;
use yii\debug\widgets\FilterBanner;
use yii\debug\widgets\GridView;
use yii\helpers\Url;
use yii\web\View;

/**
 * @var bool $hasExplain Whether the database driver supports EXPLAIN.
 * @var DbPanel $panel Panel providing the detail content.
 * @var ArrayDataProvider $queryDataProvider Data provider for the query GridView widget.
 * @var DbSearch $searchModel Search model for filtering the database query grid.
 * @var View $this View component instance.
 */
$rows = $panel->getRows();

$hasQueries = $rows !== [];

/** @var list<QueryRow> $pageRows */
$pageRows = array_values($queryDataProvider->getModels());

$nPlusOneFindings = NPlusOneDetector::detect($pageRows);

/** @var array<int, NPlusOneFinding> $nPlusOneBySequence */
$nPlusOneBySequence = [];

foreach ($nPlusOneFindings as $finding) {
    foreach ($finding->sequences as $sequence) {
        $nPlusOneBySequence[$sequence] = $finding;
    }
}

$nPlusOneSummary = DbQueryRenderer::renderNPlusOneSummary($nPlusOneFindings, DbMessage::PAGE_SCOPE->value);

$tag = $panel->tag;

$explainUrlBuilder = static fn(int $seq): string => Url::to(
    Module::route('db-explain', ['seq' => $seq, 'tag' => $tag]),
);
?>
<?= DbSummaryRenderer::render($panel->getSummary(), $hasQueries ? GridViewConfig::pageSizeSelectorHtml() : null) ?>
<?php if (!$hasQueries): ?>
    <?= EmptyState::card(
        DbMessage::EMPTY_HEADLINE->value,
        P::tag()->content(DbMessage::EMPTY_EXPLANATION),
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
<?= $nPlusOneSummary ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?php if ($queryDataProvider->getTotalCount() === 0): ?>
    <?= EmptyState::card(
        DbMessage::NO_MATCH_HEADLINE->value,
        P::tag()->content(DbMessage::NO_MATCH_EXPLANATION),
    ) ?>
    <?php return; ?>
<?php endif; ?>
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
                'label' => DbMessage::TYPE->value,
                'format' => 'raw',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderTypeCell($data),
                'filter' => $panel->getTypes(),
                'filterInputOptions' => ['class' => 'yii-debug-select'],
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
            ],
            [
                'attribute' => 'seq',
                'label' => DbMessage::TIME->value,
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderTimeCell($data),
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
            ],
            [
                'attribute' => 'duration',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderDurationCell($data),
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
            ],
            [
                'attribute' => 'rows',
                'label' => DbMessage::ROWS->value,
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderRowsCell($data),
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
            ],
            [
                'attribute' => 'duplicate',
                'label' => DbMessage::DUPLICATE->value,
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
            ],
            [
                'attribute' => 'query',
                'value' => static fn(QueryRow $data): string => DbQueryRenderer::renderQueryCell(
                    $data,
                    $panel->getTraceLine(...),
                    $hasExplain,
                    $explainUrlBuilder,
                    $nPlusOneBySequence[$data->seq] ?? null,
                ),
                'format' => 'raw',
                'filterInputOptions' => ['class' => 'yii-debug-input'],
            ],
        ],
    ],
) ?>
<?php if ($hasExplain): ?>
    <?= Div::tag()
        ->class('yii-debug-db-explain-all')
        ->html(
            Button::tag()
                ->addAriaAttribute('expanded', 'false')
                ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm yii-debug-db-explain-all-toggle')
                ->content(DbMessage::EXPLAIN_ALL)
                ->type(ButtonType::BUTTON),
        ) ?>
<?php endif;
