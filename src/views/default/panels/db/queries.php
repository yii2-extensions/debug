<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\DbAsset;
use yii\debug\GridViewConfig;
use yii\debug\models\search\Db;
use yii\debug\panels\db\{DbQueryRenderer, QueryRowNormalizer};
use yii\debug\panels\DbPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\{Html, Url};
use yii\web\View;

/**
 * @var ArrayDataProvider $queryDataProvider
 * @var bool $hasExplain
 * @var DbPanel $panel
 * @var Db $searchModel
 * @var int $sumDuplicates
 * @var View $this
 */

$timings = $panel->calculateTimings();

$totalMs = number_format(array_sum(array_column($timings, 'duration')) * 1000, 3);

$tag = $panel->tag;

$explainUrlBuilder = static fn(int $seq): string => Url::to(['db-explain', 'seq' => $seq, 'tag' => $tag]);
?>
<header class="yii-debug-grid-summary">
    <span><strong><?= count($timings) ?></strong> queries</span>
    <span class="yii-debug-grid-summary-sep">·</span>
    <span><strong><?= $totalMs ?></strong> ms total</span>
    <?php if ($sumDuplicates > 0): ?>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span class="yii-debug-grid-summary-stat-warn">
            <strong><?= $sumDuplicates ?></strong> duplicated
        </span>
    <?php endif; ?>
    <?= GridViewConfig::pageSizeSelectorHtml() ?>
</header>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?php

echo GridView::widget(
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
);

if ($hasExplain) {
    DbAsset::register($this);

    echo Html::tag(
        'div',
        Html::a('[+] Explain all', 'javascript:;'),
        ['class' => 'yii-debug-db-explain-all'],
    );
}
