<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\base\Event;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\panels\event\{EventCellRenderer, EventRowNormalizer};
use yii\debug\panels\EventPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var EventPanel $panel Panel providing the detail content.
 * @var Event $searchModel Search model for filtering the event grid.
 */
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Events') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(
        Span::tag()->html(
            Strong::tag()->content((string) $dataProvider->getTotalCount()),
            ' events captured',
        ),
        GridViewConfig::pageSizeSelectorHtml(),
    ) ?>
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
