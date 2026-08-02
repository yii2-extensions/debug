<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\List\{Li, Ol};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use yii\debug\helpers\{Coerce, EmptyState, Icon};
use yii\debug\panels\asset\{AssetCardRenderer, AssetSummary, ViteManifest};

/**
 * @var AssetSummary $summary Typed asset bundle summary.
 * @var ViteManifest|null $vite Vite manifest snapshot, or `null` when no Vite bridge is registered.
 */
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
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Asset Bundles') ?>
<?= Header::tag()
    ->class('yii-debug-asset-stats')
    ->html(...$statBlocks) ?>

<?php if ($vite !== null): ?>
    <?php
    $viteEntries = $vite->chunks;
    $viteDevMode = $vite->devMode;
    $viteDevServer = $vite->devServerUrl;
    $viteBaseUrl = $vite->baseUrl;
    $viteManifest = $vite->manifestPath;

    $viteRows = [];
    $viteIndex = 1;

    foreach ($viteEntries as $chunk) {
        $entryBadge = $chunk->isEntry
            ? Span::tag()->class('yii-debug-badge yii-debug-badge-success')->content('entry')->render()
            : '—';

        $viteRows[] = Tr::tag()->html(
            Td::tag()->content((string) $viteIndex),
            Td::tag()
                ->class('yii-debug-cell-mono')
                ->html(Strong::tag()->content($chunk->name)),
            Td::tag()
                ->class('yii-debug-cell-mono')
                ->content($chunk->file !== '' ? $chunk->file : '—'),
            Td::tag()
                ->class('yii-debug-cell-numeric')
                ->content((string) $chunk->cssCount),
            Td::tag()
                ->class('yii-debug-cell-numeric')
                ->content((string) $chunk->imports),
            Td::tag()
                ->class('yii-debug-cell-pill')
                ->html($entryBadge),
        );

        $viteIndex++;
    }
    ?>
    <?= H2::tag()->content('Vite') ?>
    <?= Div::tag()
        ->class('yii-debug-table-wrap')
        ->html(
            Table::tag()
                ->class('yii-debug-table yii-debug-table-mono')
                ->html(
                    Tbody::tag()->html(
                        Tr::tag()->html(
                            Th::tag()->scope('row')->content('Mode'),
                            Td::tag()->content(
                                $viteDevMode
                                    ? 'Dev server' . ($viteDevServer !== null ? " ({$viteDevServer})" : '')
                                    : 'Build manifest',
                            ),
                        ),
                        Tr::tag()->html(
                            Th::tag()->scope('row')->content('Base URL'),
                            Td::tag()->content($viteBaseUrl !== '' ? $viteBaseUrl : '—'),
                        ),
                        Tr::tag()->html(
                            Th::tag()->scope('row')->content('Manifest'),
                            Td::tag()->content($viteManifest !== '' ? $viteManifest : '—'),
                        ),
                    ),
                ),
        ) ?>
    <?php if ($viteRows !== []): ?>
        <?= Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()->html(
                            Tr::tag()->html(
                                Th::tag()->scope('col')->content('#'),
                                Th::tag()->scope('col')->content('Chunk'),
                                Th::tag()->scope('col')->content('Output'),
                                Th::tag()->scope('col')->content('CSS'),
                                Th::tag()->scope('col')->content('Imports'),
                                Th::tag()->scope('col')->content('Entry'),
                            ),
                        ),
                        Tbody::tag()->html(...$viteRows),
                    ),
            ) ?>
    <?php elseif ($viteDevMode === false): ?>
        <?= P::tag()->content('The Vite manifest is missing or empty — run the front-end build to populate it.') ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($summary->isEmpty()): ?>
    <?= EmptyState::card(
        'No asset bundles loaded',
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
$items = [];

foreach ($summary->bundles as $bundle) {
    $items[] = Li::tag()
        ->class('yii-debug-asset-list-item')
        ->html(AssetCardRenderer::renderCard($bundle, $summary));
}
?>
<?= Ol::tag()
    ->class('yii-debug-asset-list')
    ->html(...$items);
