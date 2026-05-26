<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\{DbAsset, GridViewConfig};
use yii\debug\models\search\DbSearch;
use yii\debug\panels\db\{DbQueryRenderer, QueryRowNormalizer};
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
$timings = $panel->calculateTimings();

$totalMs = number_format(array_sum(array_column($timings, 'duration')) * 1000, 3);

$tag = $panel->tag;

$explainUrlBuilder = static fn(int $seq): string => Url::to(['db-explain', 'seq' => $seq, 'tag' => $tag]);

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) count($timings)),
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

$summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
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
                    'value' => static fn(mixed $data): string => DbQueryRenderer::renderTypeCell(
                        QueryRowNormalizer::from($data),
                    ),
                    'filter' => $panel->getTypes(),
                    'options' => ['width' => '8%'],
                ],
                [
                    'attribute' => 'seq',
                    'label' => 'Time',
                    'value' => static fn(mixed $data): string => DbQueryRenderer::renderTimeCell(
                        QueryRowNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical'],
                    'options' => ['width' => '10%'],
                ],
                [
                    'attribute' => 'duration',
                    'value' => static fn(mixed $data): string => DbQueryRenderer::renderDurationCell(
                        QueryRowNormalizer::from($data),
                    ),
                    'options' => ['width' => '8%'],
                    'headerOptions' => ['class' => 'sort-numerical'],
                ],
                [
                    'attribute' => 'rows',
                    'label' => 'Rows',
                    'value' => static fn(mixed $data): string => DbQueryRenderer::renderRowsCell(
                        QueryRowNormalizer::from($data),
                    ),
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
                    'value' => static fn(mixed $data): string => DbQueryRenderer::renderQueryCell(
                        QueryRowNormalizer::from($data),
                        $panel,
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
    <?php DbAsset::register($this); ?>
    <?= Div::tag()
        ->class('yii-debug-db-explain-all')
        ->html(
            A::tag()->href('javascript:;')->content('[+] Explain all'),
        ) ?>
<?php endif;
