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
use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventInspectorRenderer, EventRow, EventSequence};
use yii\debug\panels\EventPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var EventPanel $panel Panel providing the detail content.
 * @var EventSearch $searchModel Search model for filtering the event grid.
 */
$hasEvents = $panel->hasEvents();

/** @var list<EventRow> $models */
$models = $dataProvider->allModels;

$staticCount = EventRow::staticCount($models);

$summaryItems = [
    Span::tag()->html(
        Strong::tag()->content((string) count($models)),
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
                'No events reached the global debug listener. Events stopped by instance handlers, or fired '
                . 'outside the capture window, may be absent.',
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
<?php
$sequence = new EventSequence($panel->getEvents());

$filterUrl = static function (string $attribute, string $value) use ($panel, $searchModel): string {
    $params = [];

    foreach (Yii::$app->request->get() as $key => $valueFromQuery) {
        if (is_string($key)) {
            $params[$key] = $valueFromQuery;
        }
    }

    unset($params['page']);

    $filters = $searchModel->getAttributes(['name', 'class', 'senderClass', 'isStatic']);

    $filters[$attribute] = $value;
    $params['Event'] = $filters;

    return $panel->getUrl($params);
};
?>
<?= EventInspectorRenderer::renderControls(
    $panel->getEvents(),
    $filterUrl,
    'name',
    'Observed by the global Yii listener. Events stopped by instance handlers may be absent; order is observation order.',
) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'afterRow' => static fn(EventRow $data): string => EventInspectorRenderer::renderDetailRow($data, $sequence, 6),
        'id' => 'event-panel-detailed-grid',
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-event'],
        'filterModel' => $searchModel,
        'filterUrl' => $panel->getUrl(),
        'columns' => [
            [
                'label' => '#',
                'value' => static fn(EventRow $data): int => $sequence->index($data),
                'contentOptions' => ['class' => 'yii-debug-col-num'],
                'headerOptions' => ['class' => 'yii-debug-col-num'],
            ],
            [
                'attribute' => 'time',
                'value' => static fn(EventRow $data): string => EventInspectorRenderer::renderTimeCell($data, $sequence),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-event-time-cell'],
                'headerOptions' => ['class' => 'sort-numerical'],
                'options' => ['width' => '10%'],
            ],
            [
                'attribute' => 'name',
                'label' => 'Event',
                'value' => static fn(EventRow $data): string => EventInspectorRenderer::renderEventCell($data, $sequence),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-event-cell'],
                'filterInputOptions' => ['class' => 'yii-debug-input', 'aria-label' => 'Filter by event name'],
            ],
            [
                'attribute' => 'class',
                'filterInputOptions' => ['class' => 'yii-debug-input', 'aria-label' => 'Filter by event class'],
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderClassCell($data),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-cell-mono'],
            ],
            [
                'attribute' => 'senderClass',
                'filterInputOptions' => ['class' => 'yii-debug-input', 'aria-label' => 'Filter by sender'],
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderSenderCell($data),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-cell-mono'],
            ],
            [
                'attribute' => 'isStatic',
                'filterInputOptions' => ['class' => 'yii-debug-input', 'aria-label' => 'Filter by static events'],
                'value' => static fn(EventRow $data): string => EventCellRenderer::renderStaticCell($data),
                'format' => 'raw',
                'filter' => ['1' => 'Yes', '0' => 'No'],
                'contentOptions' => ['class' => 'yii-debug-cell-pill'],
                'options' => ['width' => '8%'],
            ],
        ],
    ],
);
