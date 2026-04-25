<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\View;

/** @var yii\debug\panels\DbPanel $panel */
/** @var yii\debug\models\search\Db $searchModel */
/** @var yii\data\ArrayDataProvider $queryDataProvider */
/** @var yii\data\ArrayDataProvider $callerDataProvider */
/** @var bool $hasExplain */
/** @var int $sumDuplicates */
/** @var View $this */

?>

<h1><?= Html::encode($panel->getName()) ?></h1>

<?php
if (Yii::$app->log->traceLevel < 1) {
    echo '<div class="yii-debug-callout yii-debug-callout--warning">Check application configuration section [log] for <b>traceLevel</b></div>';
}

if ($sumDuplicates === 1) {
    echo "<p><b>$sumDuplicates</b> duplicated query found.</p>";
} elseif ($sumDuplicates > 1) {
    echo "<p><b>$sumDuplicates</b> duplicated queries found.</p>";
}


$excessiveCallers = $panel->getExcessiveCallers();
$numExcessiveCallers = count($excessiveCallers);
if ($numExcessiveCallers) {
    $excessiveCallersInfo = "<p><b>$numExcessiveCallers</b> excessive caller" . ($numExcessiveCallers > 1 ? 's' : '')
        . ' making ' . array_sum($excessiveCallers) . ' cals.</p>';

    echo $excessiveCallersInfo;
}

$items = [];

$items['nav'][] = 'Queries';
$items['content'][] = $this->render('queries', [
    'panel' => $panel,
    'searchModel' => $searchModel,
    'queryDataProvider' => $queryDataProvider,
    'hasExplain' => $hasExplain,
    'sumDuplicates' => $sumDuplicates,
]);

$items['nav'][] = 'Callers' . (
    !empty($excessiveCallersInfo)
            ? ' ' . Html::tag('span', '&#x26a0;', ['title' => strip_tags($excessiveCallersInfo)])
            : ''
);
$items['content'][] = $this->render('callers', [
    'panel' => $panel,
    'searchModel' => $searchModel,
    'callerDataProvider' => $callerDataProvider,
    'hasExplain' => $hasExplain,
    'sumDuplicates' => $sumDuplicates,
]);

?>
<ul class="yii-debug-tabs">
    <?php
    foreach ($items['nav'] as $k => $item) {
        echo Html::tag(
            'li',
            Html::a($item, '#u-tab-' . $k, [
                'class' => $k === 0 ? 'yii-debug-tab__link is-active' : 'yii-debug-tab__link',
                'data-yii-debug-toggle' => 'tab',
                'role' => 'tab',
                'aria-controls' => 'u-tab-' . $k,
                'aria-selected' => $k === 0 ? 'true' : 'false',
            ]),
            [
                'class' => 'yii-debug-tab',
            ],
        );
    }
?>
</ul>
<div class="yii-debug-tab-content">
    <?php
foreach ($items['content'] as $k => $item) {
    echo Html::tag('div', $item, [
        'class' => $k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel',
        'id' => 'u-tab-' . $k,
    ]);
}
?>
</div>
