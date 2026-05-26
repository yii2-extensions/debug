<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use yii\debug\models\search\TimelineSearch;
use yii\debug\models\timeline\DataProvider;
use yii\debug\panels\timeline\TimelineRenderer;
use yii\debug\panels\TimelinePanel;
use yii\debug\TimelineAsset;
use yii\web\View;

/**
 * @var DataProvider $dataProvider Data provider for the timeline chart.
 * @var TimelinePanel $panel Panel providing the detail content.
 * @var TimelineSearch $searchModel Search model for filtering the timeline grid.
 * @var View $this View component instance.
 */
TimelineAsset::register($this);
?>
<?= H1::tag()->class('yii-debug-sr-only')->content('Timeline') ?>
<?= TimelineRenderer::renderSummary($panel, $dataProvider) ?>
<?= TimelineRenderer::renderFilterForm($panel, $searchModel) ?>
<?= TimelineRenderer::renderEmptyHint($panel, $dataProvider) ?>
<?= TimelineRenderer::renderChart($panel, $dataProvider);
