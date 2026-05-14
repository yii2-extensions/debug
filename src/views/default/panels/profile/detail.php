<?php

declare(strict_types=1);

use UIAwesome\Html\Palpable\A;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\ProfileSearch;
use yii\debug\panels\profile\{ProfileCellRenderer, ProfileRowNormalizer};
use yii\debug\panels\ProfilingPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider
 * @var ProfileSearch $searchModel
 * @var ProfilingPanel $panel
 * @var string $memory
 * @var string $time
 * @var string $timelineUrl
 */

$hasProfileBlocks = $dataProvider->getTotalCount() > 0;
?>
<h1 class="yii-debug-sr-only">Performance Profiling</h1>
<header class="yii-debug-grid-summary">
    <span><strong><?= $time ?></strong> total</span>
    <span class="yii-debug-grid-summary-sep">·</span>
    <span><strong><?= $memory ?></strong> peak</span>
    <?php if ($hasProfileBlocks): ?>
        <span class="yii-debug-grid-summary-sep">·</span>
        <?= A::tag()->href($timelineUrl)->content('Open timeline')->render() ?>
        <?= GridViewConfig::pageSizeSelectorHtml() ?>
    <?php endif; ?>
</header>
<?= $hasProfileBlocks ? FilterBanner::widget(['searchModel' => $searchModel]) : '' ?>
<?php if (!$hasProfileBlocks): ?>
    <div class="yii-debug-empty-state">
        <h2>No profile blocks captured</h2>
        <p>This request did not produce any <code>Yii::beginProfile()</code> / <code>Yii::endProfile()</code> blocks, so the timing table is empty.</p>
        <p>To populate this view, wrap interesting sections of code with profile markers:</p>
        <pre class="yii-debug-empty-state-code">Yii::beginProfile('my-token');
// …work…
Yii::endProfile('my-token');</pre>
        <p>Database queries are profiled automatically when the <code>db</code> component is used, so any request hitting the database will show entries here.</p>
    </div>
<?php else: ?>
<?php echo GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'profile-panel-detailed-grid',
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'columns' => [
            [
                'attribute' => 'seq',
                'label' => 'Time',
                'value' => static fn(mixed $data): string => ProfileCellRenderer::renderTimeCell(
                    ProfileRowNormalizer::from($data),
                ),
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'duration',
                'value' => static fn(mixed $data): string => ProfileCellRenderer::renderDurationCell(
                    ProfileRowNormalizer::from($data),
                ),
                'options' => ['width' => '10%'],
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            'category',
            [
                'attribute' => 'info',
                'value' => static fn(mixed $data): string => ProfileCellRenderer::renderInfoCell(
                    ProfileRowNormalizer::from($data),
                ),
                'format' => 'html',
                'options' => ['width' => '60%'],
            ],
        ],
    ],
); ?>
<?php endif;
