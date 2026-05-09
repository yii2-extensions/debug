<?php

declare(strict_types=1);

use yii\base\Event;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\panels\event\{EventCellRenderer, EventRowNormalizer};
use yii\debug\panels\EventPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider
 * @var Event $searchModel
 * @var EventPanel $panel
 */
?>
<h1 class="yii-debug-sr-only">Events</h1>
<header class="yii-debug-grid-summary">
    <span><strong><?= $dataProvider->getTotalCount() ?></strong> events captured</span>
    <?= GridViewConfig::pageSizeSelectorHtml() ?>
</header>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'log-panel-detailed-event',
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'columns' => [
            [
                'attribute' => 'time',
                'value' => static fn(mixed $data): string => EventCellRenderer::renderTimeCell(
                    EventRowNormalizer::from($data),
                ),
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'name',
            ],
            [
                'attribute' => 'class',
            ],
            [
                'header' => 'Sender',
                'attribute' => 'senderClass',
                'value' => static fn(mixed $data): string => EventCellRenderer::renderSenderCell(
                    EventRowNormalizer::from($data),
                ),
            ],
            [
                'header' => 'Static',
                'attribute' => 'isStatic',
                'format' => 'boolean',
            ],
        ],
    ],
);
