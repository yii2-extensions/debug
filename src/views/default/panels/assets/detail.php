<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\List\{Li, Ol};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\debug\helpers\Icon;
use yii\debug\panels\asset\{AssetCardRenderer, AssetSummary};

/**
 * @var AssetSummary $summary Typed asset bundle summary.
 */
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Asset Bundles') ?>

<?php if ($summary->isEmpty()): ?>
    <?= Div::tag()
        ->class('yii-debug-empty-state')
        ->html(
            H2::tag()
                ->content('No asset bundles loaded'),
            P::tag()
                ->html(
                    'This request did not register any ',
                    Code::tag()->content('yii\\web\\AssetBundle'),
                    ' via ',
                    Code::tag()->content('register()'),
                    ', so the inventory is empty.',
                ),
            P::tag()
                ->html(
                    'Bundles appear here when something in the request actively pulls them in — typically a layout or view '
                    . 'that calls a bundle\'s ',
                    Code::tag()->content('register()'),
                    ', or any bundle reached transitively through the ',
                    Code::tag()->content('depends'),
                    ' chain.',
                ),
        ) ?>
    <?php return; ?>
<?php endif; ?>

<?php
$stats = [
    ['bundles', 'asset', $summary->totalBundles, 'bundle' . ($summary->totalBundles === 1 ? '' : 's')],
    ['css', 'brand-css3', $summary->totalCss, 'css'],
    ['js', 'brand-javascript', $summary->totalJs, 'js'],
    ['deps', 'link', $summary->totalDeps, 'link' . ($summary->totalDeps === 1 ? '' : 's')],
];

$statBlocks = [];

foreach ($stats as [$kind, $icon, $value, $label]) {
    $statBlocks[] = Div::tag()
        ->addDataAttribute('kind', $kind)
        ->class('yii-debug-asset-stat')
        ->html(
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class('yii-debug-asset-stat-icon')
                ->html(Icon::render($icon)),
            Strong::tag()
                ->class('yii-debug-asset-stat-value')
                ->content((string) $value),
            Span::tag()
                ->class('yii-debug-asset-stat-label')
                ->content($label),
        );
}

$items = [];

foreach ($summary->bundles as $bundle) {
    $items[] = Li::tag()
        ->class('yii-debug-asset-list-item')
        ->html(AssetCardRenderer::renderCard($bundle, $summary));
}
?>
<?= Header::tag()
    ->class('yii-debug-asset-stats')
    ->html(...$statBlocks) ?>
<?= Ol::tag()
    ->class('yii-debug-asset-list')
    ->html(...$items);
