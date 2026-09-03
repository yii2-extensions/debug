<?php

declare(strict_types=1);

use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Profile\{ProfileCellRenderer, ProfileRow};
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputNumber, InputText};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Code, Label, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\ProfileSearch;
use yii\debug\panels\ProfilingPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var string $filterAction URL submitted by the shared profiling filter form.
 * @var array<string, string> $filterHiddenParams Adapter-owned route and display parameters.
 * @var string $memory Peak memory consumption.
 * @var ProfilingPanel $panel Panel providing the detail content.
 * @var ProfileSearch $searchModel Search model shared by the Timeline and details grid.
 * @var string $time Total request processing time.
 * @var string $timeline Rendered Timeline chart or its unavailable state.
 */
$capturedModels = $panel->getModels();

$capturedCount = count($capturedModels);

$visibleCount = $dataProvider->getTotalCount();

$spanLabel = ' span' . ($capturedCount === 1 ? '' : 's');
$countLabel = $visibleCount === $capturedCount ? $spanLabel : " of {$capturedCount}{$spanLabel}";

$maxDuration = ProfileRow::maxDuration($capturedModels);

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) $visibleCount),
            $countLabel,
        ),
    Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·'),
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
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Performance Profiling') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>
<?php if ($capturedModels === []): ?>
    <?= EmptyState::card(
        'No profiling data captured',
        P::tag()
            ->html(
                'This request did not produce any ',
                Code::tag()->content('Yii::beginProfile()'),
                ' / ',
                Code::tag()->content('Yii::endProfile()'),
                ' spans, so the Timeline and details are empty.',
            ),
        P::tag()->content('To populate this view, wrap interesting sections of code with profile markers:'),
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
<?php
$filterFields = [];

foreach ($filterHiddenParams as $name => $value) {
    $filterFields[] = InputHidden::tag()->name($name)->value($value);
}

$filterFields[] = Div::tag()
    ->class('yii-debug-tl-field')
    ->html(
        Label::tag()
            ->content('Min duration (ms)')
            ->for('profile-duration'),
        InputNumber::tag()
            ->id('profile-duration')
            ->min(0)
            ->name('Profile[duration]')
            ->placeholder('0')
            ->step(0.1)
            ->value($searchModel->duration),
    );
$filterFields[] = Div::tag()
    ->class('yii-debug-tl-field yii-debug-tl-field-grow')
    ->html(
        Label::tag()
            ->content('Category')
            ->for('profile-category'),
        InputText::tag()
            ->id('profile-category')
            ->name('Profile[category]')
            ->placeholder('yii\\db\\Command::query')
            ->value($searchModel->category),
    );
$filterFields[] = Div::tag()
    ->class('yii-debug-tl-field yii-debug-tl-field-grow')
    ->html(
        Label::tag()
            ->content('Info')
            ->for('profile-info'),
        InputText::tag()
            ->id('profile-info')
            ->name('Profile[info]')
            ->placeholder('SELECT')
            ->value($searchModel->info),
    );
$filterFields[] = Button::tag()
    ->class('yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm')
    ->content('Apply')
    ->type('submit');
?>
<?= Form::tag()
    ->action($filterAction)
    ->addAriaAttribute('label', 'Profiling filters')
    ->class('yii-debug-tl-filter')
    ->html(...$filterFields)
    ->method('get') ?>
<?= FilterBanner::widget(
    [
        'activeFilters' => $searchModel->getAttributes(),
        'searchModel' => $searchModel,
    ],
) ?>
<?php if ($visibleCount === 0): ?>
    <?= EmptyState::card(
        'No spans match the active filters',
        P::tag()->content('Adjust or clear the filters to show the captured spans.'),
    ) ?>
    <?php return; ?>
<?php endif; ?>
<?= H2::tag()->content('Timeline') ?>
<?= $timeline ?>
<?= Header::tag()
    ->class('yii-debug-section-header')
    ->html(
        H2::tag()->content('Details'),
        GridViewConfig::pageSizeSelectorHtml(),
    ) ?>
<?= GridView::widget(
    [
        ...GridViewConfig::defaults(),
        'dataProvider' => $dataProvider,
        'id' => 'profile-panel-detailed-grid',
        'filterModel' => null,
        'options' => ['class' => 'yii-debug-grid yii-debug-grid-profile'],
        'columns' => [
            [
                'attribute' => 'seq',
                'label' => 'Time',
                'value' => static fn(ProfileRow $data): string => ProfileCellRenderer::renderTimeCell($data),
                'format' => 'raw',
                'headerOptions' => ['class' => 'sort-numerical'],
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-nowrap'],
            ],
            [
                'attribute' => 'duration',
                'value' => static fn(ProfileRow $data): string => ProfileCellRenderer::renderDurationCell(
                    $data,
                    $maxDuration,
                ),
                'format' => 'raw',
                'options' => ['width' => '10%'],
                'headerOptions' => ['class' => 'sort-numerical'],
            ],
            [
                'attribute' => 'category',
                'value' => static fn(ProfileRow $data): string => ProfileCellRenderer::renderCategoryCell($data),
                'format' => 'raw',
                'contentOptions' => ['class' => 'yii-debug-cell-mono yii-debug-cell-fqcn'],
            ],
            [
                'attribute' => 'info',
                'value' => static fn(ProfileRow $data): string => ProfileCellRenderer::renderInfoCell($data),
                'format' => 'raw',
                'options' => ['width' => '60%'],
            ],
        ],
    ],
); ?>
