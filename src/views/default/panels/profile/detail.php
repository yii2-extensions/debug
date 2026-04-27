<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\debug\panels\ProfilingPanel $panel */
/** @var yii\debug\models\search\Profile $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var int $time */
/** @var int $memory */

$hasProfileBlocks = $dataProvider->getTotalCount() > 0;
?>
    <h1 class="yii-debug-sr-only">Performance Profiling</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $time ?></strong> total</span>
        <span class="yii-debug-grid-summary-sep">·</span>
        <span><strong><?= $memory ?></strong> peak</span>
        <?php if ($hasProfileBlocks): ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <?= Html::a('Open timeline', [
                '/' . $panel->module->getUniqueId() . '/default/view',
                'panel' => 'timeline',
                'tag' => $panel->tag,
            ]) ?>
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
<?php echo GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $dataProvider,
    'id' => 'profile-panel-detailed-grid',
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        [
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => static function (array $data): string {
                $timestamp = is_numeric($data['timestamp'] ?? null) ? (float) $data['timestamp'] : 0.0;
                $timeInSeconds = $timestamp / 1000;
                $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

                return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        [
            'attribute' => 'duration',
            'value' => static function (array $data): string {
                $duration = is_numeric($data['duration'] ?? null) ? (float) $data['duration'] : 0.0;
                return sprintf('%.1f ms', $duration);
            },
            'options' => ['width' => '10%'],
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        'category',
        [
            'attribute' => 'info',
            'value' => static function (array $data): string {
                $level = is_int($data['level'] ?? null) ? $data['level'] : 0;
                $info = is_string($data['info'] ?? null) ? $data['info'] : '';
                return str_repeat('<span class="yii-debug-indent">→</span>', max(0, $level)) . Html::encode($info);
            },
            'format' => 'html',
            'options' => ['width' => '60%'],
        ],
    ],
])); ?>
<?php endif; ?>
