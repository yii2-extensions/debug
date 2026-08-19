<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{P, Pre};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use PHPForge\Debug\Helper\EmptyState;
use yii\debug\models\search\EventSearch;
use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventRow};
use yii\debug\panels\EventPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var EventPanel $panel Panel providing the detail content.
 * @var EventSearch $searchModel Search model for filtering the event grid.
 */
$hasEvents = $panel->hasEvents();

$models = $panel->getEvents();

$staticCount = EventRow::staticCount($models);

$summaryItems = [
    Span::tag()->html(
        Strong::tag()->content((string) $dataProvider->getTotalCount()),
        ' events',
    ),
    Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·'),
    Span::tag()->html(
        Strong::tag()->content((string) EventRow::distinctClassCount($models)),
        ' classes',
    ),
];

if ($staticCount > 0) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()->html(
        Strong::tag()->content((string) $staticCount),
        ' static',
    );
}

if ($hasEvents) {
    $summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
}
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Events') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?php if (!$hasEvents): ?>
    <?= EmptyState::card(
        'No events triggered in this request',
        P::tag()
            ->content(
                'The event panel records every event the framework or the application fires through a wildcard '
                . 'listener, so this request completed without triggering any.',
            ),
        P::tag()
            ->content('Any component that attaches a handler and triggers an event populates this view:'),
        Pre::tag()
            ->class('yii-debug-empty-state-code')
            ->content("\$component->on('myEvent', \$handler);\n\$component->trigger('myEvent');"),
    ) ?>
    <?php return; ?>
<?php endif; ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'event-panel-detailed-grid',
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-event'],
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'columns' => [
            [
                'attribute' => 'time',
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderTimeCell($data),
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
                'headerOptions' => ['class' => 'sort-numerical'],
                'options' => ['width' => '10%'],
            ],
            [
                'attribute' => 'name',
                'contentOptions' => ['class' => 'yii-debug-cell-mono'],
            ],
            [
                'attribute' => 'class',
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderClassCell($data),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-cell-mono'],
            ],
            [
                'attribute' => 'senderClass',
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderSenderCell($data),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-cell-mono'],
            ],
            [
                'attribute' => 'isStatic',
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderStaticCell($data),
                'format' => 'raw',
                'filter' => ['1' => 'Yes', '0' => 'No'],
                'contentOptions' => ['class' => 'yii-debug-cell-pill'],
                'options' => ['width' => '8%'],
            ],
        ],
    ],
);
