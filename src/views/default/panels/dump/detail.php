<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{P, Pre};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\helpers\EmptyState;
use yii\debug\models\search\LogSearch;
use yii\debug\panels\dump\{DumpCardRenderer, DumpRowNormalizer};
use yii\debug\panels\DumpPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var DumpPanel $panel Panel providing the detail content.
 * @var LogSearch $searchModel Search model for filtering the dump grid.
 */
$hasDumps = is_array($panel->data) && $panel->data !== [];

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) $dataProvider->getTotalCount()),
            ' dumps captured',
        ),
];

if ($hasDumps) {
    $summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
}
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Dump') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?php if (!$hasDumps): ?>
    <?= EmptyState::card(
        'No variables dumped in this request',
        P::tag()
            ->html(
                'The dump panel collects the trace-level messages logged through ',
                Code::tag()->content('Yii::debug()'),
                ', so nothing was captured here.',
            ),
        P::tag()
            ->content('To populate this view, dump values anywhere in the request cycle:'),
        Pre::tag()
            ->class('yii-debug-empty-state-code')
            ->content("Yii::debug(\$user->attributes);\nYii::debug(\$query->createCommand()->getRawSql());"),
        P::tag()
            ->html(
                'Only messages in the configured categories are captured (the ',
                Code::tag()->content('application'),
                ' category by default — the ',
                Code::tag()->content('categories'),
                ' panel option widens the net).',
            ),
    ) ?>
    <?php return; ?>
<?php endif; ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $dataProvider,
            'id' => 'dump-panel-detailed-grid',
            'options' => ['class' => 'yii-debug-grid yii-debug-grid-dump'],
            'filterModel' => $searchModel,
            'filterUrl' => $panel->getUrl(),
            'columns' => [
                [
                    'attribute' => 'category',
                    'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-muted yii-debug-nowrap'],
                    'options' => ['width' => '20%'],
                ],
                [
                    'attribute' => 'message',
                    'value' => static fn(mixed $data, mixed $key, int $index): string => DumpCardRenderer::renderMessageCell(
                        DumpRowNormalizer::from($data),
                        $panel,
                        $index,
                    ),
                    'format' => 'raw',
                    'options' => ['width' => '80%'],
                ],
            ],
        ],
    );
