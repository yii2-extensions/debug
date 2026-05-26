<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\ProfileSearch;
use yii\debug\panels\profile\{ProfileCellRenderer, ProfileRowNormalizer};
use yii\debug\panels\ProfilingPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var string $memory Peak memory consumption.
 * @var ProfilingPanel $panel Panel providing the detail content.
 * @var ProfileSearch $searchModel Search model for filtering the profile grid.
 * @var string $time Total request processing time.
 * @var string $timelineUrl URL to the Timeline panel.
 */
$hasProfileBlocks = $dataProvider->getTotalCount() > 0;

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content($time),
            ' total',
        ),
    Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·'),
    Span::tag()
        ->html(
            Strong::tag()->content($memory),
            ' peak',
        ),
];

if ($hasProfileBlocks) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = A::tag()
        ->content('Open timeline')
        ->href($timelineUrl);
    $summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
}
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Performance Profiling') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?php if (!$hasProfileBlocks): ?>
    <?= Div::tag()
        ->class('yii-debug-empty-state')
        ->html(
            H2::tag()
                ->content('No profile blocks captured'),
            P::tag()
                ->html(
                    'This request did not produce any ',
                    Code::tag()->content('Yii::beginProfile()'),
                    ' / ',
                    Code::tag()->content('Yii::endProfile()'),
                    ' blocks, so the timing table is empty.',
                ),
            P::tag()
                ->content('To populate this view, wrap interesting sections of code with profile markers:'),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content("Yii::beginProfile('my-token');\n// …work…\nYii::endProfile('my-token');"),
            P::tag()
                ->html(
                    'Database queries are profiled automatically when the ',
                    Code::tag()->content('db'),
                    ' component is used, so any request hitting the database will show entries here.',
                ),
        ) ?>
    <?php return; ?>
<?php endif; ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $dataProvider,
            'id' => 'profile-panel-detailed-grid',
            'filterModel' => $searchModel,
            'filterUrl' => $panel->getUrl(),
            'columns' => [
                [
                    'attribute' => 'seq',
                    'label' => 'Time',
                    'value' => static fn(mixed $data): string => ProfileCellRenderer::renderTimeCell(
                        ProfileRowNormalizer::from($data),
                    ),
                    'headerOptions' => ['class' => 'sort-numerical'],
                ],
                [
                    'attribute' => 'duration',
                    'value' => static fn(mixed $data): string => ProfileCellRenderer::renderDurationCell(
                        ProfileRowNormalizer::from($data),
                    ),
                    'options' => ['width' => '10%'],
                    'headerOptions' => ['class' => 'sort-numerical'],
                    ],
                'category',
                [
                    'attribute' => 'info',
                    'value' => static fn(mixed $data): string => ProfileCellRenderer::renderInfoCell(
                        ProfileRowNormalizer::from($data),
                    ),
                    'format' => 'html',
                    'options' => ['width' => '60%'],
                    ],
            ],
        ],
    );
