<?php

declare(strict_types=1);

use yii\debug\models\timeline\{DataProvider, Search};
use yii\debug\panels\timeline\TimelineRenderer;
use yii\debug\panels\TimelinePanel;
use yii\debug\TimelineAsset;
use yii\web\View;

/**
 * @var View $this
 * @var TimelinePanel $panel
 * @var Search $searchModel
 * @var DataProvider $dataProvider
 */

TimelineAsset::register($this);
?>
<h1 class="yii-debug-sr-only">Timeline</h1>

<?= TimelineRenderer::renderSummary($panel, $dataProvider) ?>
<?= TimelineRenderer::renderFilterForm($panel, $searchModel) ?>
<?= TimelineRenderer::renderEmptyHint($panel, $dataProvider) ?>
<?= TimelineRenderer::renderChart($panel, $dataProvider);
