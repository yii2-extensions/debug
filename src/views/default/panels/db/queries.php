<?php

declare(strict_types=1);

use yii\debug\DbAsset;
use yii\debug\GridViewConfig;
use yii\debug\panels\DbPanel;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/** @var yii\debug\panels\DbPanel $panel */
/** @var yii\debug\models\search\Db $searchModel */
/** @var yii\data\ArrayDataProvider $queryDataProvider */
/** @var bool $hasExplain */
/** @var int $sumDuplicates */
/** @var View $this */

$timings = $panel->calculateTimings();
$totalMs = number_format(array_sum(array_column($timings, 'duration')) * 1000, 3);
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
</header>
<?php

echo GridView::widget(array_merge(GridViewConfig::defaults(), [
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
            'value' => static function ($data): string {
                $variant = DbPanel::typeBadgeVariant((string) $data['type']);

                return Html::tag('span', Html::encode($data['type']), [
                    'class' => "yii-debug-db-type yii-debug-db-type-{$variant}",
                ]);
            },
            'filter' => $panel->getTypes(),
            'options' => ['width' => '8%'],
        ],
        [
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => function ($data) {
                $timeInSeconds = $data['timestamp'] / 1000;
                $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

                return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => ['class' => 'sort-numerical'],
            'options' => ['width' => '10%'],
        ],
        [
            'attribute' => 'duration',
            'value' => function ($data) {
                return sprintf('%.1f ms', $data['duration']);
            },
            'options' => ['width' => '8%'],
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        [
            'attribute' => 'rows',
            'label' => 'Rows',
            'value' => static function ($data): string {
                if (!isset($data['rows']) || $data['rows'] === null) {
                    return '–';
                }

                return $data['rows'] . ' ' . ($data['rows'] === 1 ? 'row' : 'rows');
            },
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
            'value' => function ($data) use ($hasExplain, $panel) {
                $query = Html::tag('div', Html::encode($data['query']), ['class' => 'yii-debug-db-sql']);

                if (!empty($data['trace'])) {
                    $query .= Html::ul($data['trace'], [
                        'class' => 'yii-debug-trace',
                        'item' => function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }

                if ($hasExplain && $panel::canBeExplained($data['type'])) {
                    $url = ['db-explain', 'seq' => $data['seq'], 'tag' => Yii::$app->controller->summary['tag']];

                    $query .= Html::beginTag('div', ['class' => 'yii-debug-db-explain']);
                    $query .= Html::a(
                        '<span class="yii-debug-db-explain-chevron" aria-hidden="true">›</span>'
                            . '<span class="yii-debug-db-explain-label">Explain</span>',
                        $url,
                        [
                            'class' => 'yii-debug-db-explain-toggle',
                            'role' => 'button',
                            'aria-expanded' => 'false',
                            'aria-label' => 'Toggle EXPLAIN output',
                        ],
                    );
                    $query .= Html::tag('div', '', ['class' => 'yii-debug-db-explain-text']);
                    $query .= Html::endTag('div');
                }

                return $query;
            },
            'format' => 'raw',
            'options' => ['width' => '60%'],
        ],
    ],
]));

if ($hasExplain) {
    DbAsset::register($this);

    echo Html::tag(
        'div',
        Html::a('[+] Explain all', 'javascript:;'),
        ['class' => 'yii-debug-db-explain-all'],
    );
}
