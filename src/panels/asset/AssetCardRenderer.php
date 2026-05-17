<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\{H2, H3};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Article, Section};
use yii\debug\helpers\Icon;
use yii\helpers\Inflector;

use function array_map;
use function strrpos;
use function substr;

/**
 * Renders the per-bundle markup for the Asset Bundles detail view.
 *
 * Stateless static helpers: every method takes the data it needs as arguments and returns the rendered HTML tree.
 *
 * Concentrates the render logic (chip pluralization, anchor resolution, wiring/depends sections) in one testable place,
 * keeping the detail view focused on page-level scaffolding.
 */
final class AssetCardRenderer
{
    /**
     * Renders one bundle as an `<article class="yii-debug-asset-card">` ready to drop into the detail view.
     *
     * @param AssetBundleView $bundle Per-bundle view-model.
     * @param AssetSummary $summary Full summary, used to resolve `#anchor` targets for cross-bundle dependency links.
     */
    public static function renderCard(AssetBundleView $bundle, AssetSummary $summary): Article
    {
        $head = self::renderHead($bundle);
        $bodyChildren = self::renderBody($bundle, $summary);

        $articleChildren = [$head];

        if ($bodyChildren !== []) {
            $articleChildren[] = Div::tag()
                ->addDataAttribute('cols', (string) $bundle->bodyCols)
                ->class('yii-debug-asset-card-body')
                ->html(...$bodyChildren);
        }

        return Article::tag()
            ->class('yii-debug-asset-card')
            ->id($bundle->id)
            ->html(...$articleChildren);
    }

    /**
     * Resolves the anchor id for a dependency name.
     *
     * Prefers the id of an already-registered bundle so cross-references jump to a real card; otherwise falls back to
     * a fresh {@see \yii\helpers\Inflector::camel2id()} pass, so the link still points to the id the bundle would get
     * if it were registered later.
     *
     * @param string $depName Fully qualified class name of the dependency.
     * @param AssetSummary $summary Already-normalized summary.
     */
    public static function resolveAnchor(string $depName, AssetSummary $summary): string
    {
        foreach ($summary->bundles as $candidate) {
            if ($candidate->name === $depName) {
                return $candidate->id;
            }
        }

        return Inflector::camel2id($depName);
    }

    /**
     * Builds the optional card body (Files and Wiring sections), collapsing to an empty list when neither applies.
     *
     * The caller drops the body wrapper entirely when the returned list is empty.
     *
     * @return list<Section> Body sections in render order.
     */
    private static function renderBody(AssetBundleView $bundle, AssetSummary $summary): array
    {
        $body = [];

        if ($bundle->hasFiles) {
            $body[] = self::renderFilesSection($bundle);
        }

        if ($bundle->hasWiring || $bundle->hasDepends) {
            $body[] = self::renderWiringSection($bundle, $summary);
        }

        return $body;
    }

    /**
     * Renders a count chip such as `<span class="yii-debug-asset-chip yii-debug-asset-chip-css"><strong>3</strong>
     * css</span>`.
     *
     * Chooses between singular and plural based on `$count`. When `$plural` is `''`, the singular form is used for
     * every count.
     */
    private static function renderChip(string $modifier, int $count, string $singular, string $plural = ''): Span
    {
        $label = ($plural === '' || $count === 1) ? $singular : $plural;

        return Span::tag()
            ->class("yii-debug-asset-chip yii-debug-asset-chip-{$modifier}")
            ->html(
                Strong::tag()->content((string) $count),
                " {$label}",
            );
    }

    /**
     * Renders one dependency link with the short name visible, the full FQCN in `title`, and the anchor resolved via
     * {@see self::resolveAnchor()}.
     */
    private static function renderDepend(string $depName, AssetSummary $summary): A
    {
        $pos = strrpos($depName, '\\');
        $shortName = $pos === false ? $depName : substr($depName, $pos + 1);

        return A::tag()
            ->class('yii-debug-asset-depend')
            ->html(
                Span::tag()
                    ->class('yii-debug-asset-depend-icon')
                    ->addAriaAttribute('hidden', 'true')
                    ->content('↳'),
                Span::tag()
                    ->class('yii-debug-asset-depend-name')
                    ->content($shortName),
            )
            ->href('#' . self::resolveAnchor($depName, $summary))
            ->title($depName);
    }

    /**
     * Renders a single file row inside a Files list.
     */
    private static function renderFile(string $type, string $label): Div
    {
        return Div::tag()
            ->class('yii-debug-asset-file')
            ->html(
                Span::tag()
                    ->class("yii-debug-asset-file-type yii-debug-asset-file-type-{$type}")
                    ->content(".{$type}"),
                Span::tag()
                    ->class('yii-debug-asset-file-name')
                    ->title($label)
                    ->content($label),
            );
    }

    /**
     * Renders the `Files` section with separate `<div class="yii-debug-asset-files">` lists for CSS and JS.
     */
    private static function renderFilesSection(AssetBundleView $bundle): Section
    {
        $fileLists = [];

        if ($bundle->css !== []) {
            $fileLists[] = Div::tag()
                ->class('yii-debug-asset-files')
                ->html(
                    ...array_map(
                        static fn(string $f): Div => self::renderFile('css', $f),
                        $bundle->css,
                    ),
                );
        }

        if ($bundle->js !== []) {
            $fileLists[] = Div::tag()
                ->class('yii-debug-asset-files')
                ->html(
                    ...array_map(
                        static fn(string $f): Div => self::renderFile('js', $f),
                        $bundle->js,
                    ),
                );
        }

        return Section::tag()
            ->class('yii-debug-asset-section')
            ->html(
                H3::tag()->class('yii-debug-asset-section-title')->content('Files'),
                ...$fileLists,
            );
    }

    /**
     * Renders the card header: bundle icon, short name, namespace prefix, and CSS/JS/deps chips.
     */
    private static function renderHead(AssetBundleView $bundle): Header
    {
        $titleChildren = [
            H2::tag()
                ->class('yii-debug-asset-card-name')
                ->content($bundle->shortName),
        ];

        if ($bundle->namespace !== '') {
            $titleChildren[] = Span::tag()
                ->class('yii-debug-asset-card-fqcn')
                ->content("{$bundle->namespace}\\");
        }

        $metaChildren = [];

        if ($bundle->cssCount > 0) {
            $metaChildren[] = self::renderChip('css', $bundle->cssCount, 'css');
        }

        if ($bundle->jsCount > 0) {
            $metaChildren[] = self::renderChip('js', $bundle->jsCount, 'js');
        }

        if ($bundle->depsCount > 0) {
            $metaChildren[] = self::renderChip('deps', $bundle->depsCount, 'dep', 'deps');
        }

        return Header::tag()
            ->class('yii-debug-asset-card-head')
            ->html(
                Span::tag()
                    ->class('yii-debug-asset-card-icon')
                    ->addAriaAttribute('hidden', 'true')
                    ->html(Icon::render('asset')),
                Div::tag()
                    ->class('yii-debug-asset-card-title')
                    ->html(...$titleChildren),
                Div::tag()
                    ->class('yii-debug-asset-card-meta')
                    ->html(...$metaChildren),
            );
    }

    /**
     * Renders one `<dt>label</dt><dd>value</dd>` row inside the wiring `<dl>`.
     */
    private static function renderWiringRow(string $label, string $value): Div
    {
        return Div::tag()
            ->class('yii-debug-asset-wiring-row')
            ->html(
                Dt::tag()
                    ->class('yii-debug-asset-wiring-label')
                    ->content($label),
                Dd::tag()
                    ->class('yii-debug-asset-wiring-value')
                    ->content($value),
            );
    }

    /**
     * Renders the `Wiring` section combining the `source` / `base` / `url` rows and the `Depends on N` cross-link list.
     *
     * The caller guarantees that at least one of `hasWiring` / `hasDepends` is `true`.
     */
    private static function renderWiringSection(AssetBundleView $bundle, AssetSummary $summary): Section
    {
        $sectionChildren = [
            H3::tag()
                ->class('yii-debug-asset-section-title')
                ->content('Wiring'),
        ];

        if ($bundle->hasWiring) {
            $rows = [];

            if ($bundle->sourcePath !== '') {
                $rows[] = self::renderWiringRow('source', $bundle->sourcePath);
            }

            if ($bundle->basePath !== '') {
                $rows[] = self::renderWiringRow('base', $bundle->basePath);
            }

            if ($bundle->baseUrl !== '') {
                $rows[] = self::renderWiringRow('url', $bundle->baseUrl);
            }

            $sectionChildren[] = Dl::tag()
                ->class('yii-debug-asset-wiring')
                ->html(...$rows);
        }

        if ($bundle->hasDepends) {
            $sectionChildren[] = Div::tag()
                ->class('yii-debug-asset-depends')
                ->html(
                    Span::tag()
                        ->class('yii-debug-asset-depends-label')
                        ->content('Depends on ' . $bundle->depsCount),
                    Div::tag()
                        ->class('yii-debug-asset-depends-list')
                        ->html(
                            ...array_map(
                                static fn(string $dep): A => self::renderDepend($dep, $summary),
                                $bundle->depends,
                            ),
                        ),
                );
        }

        return Section::tag()
            ->class('yii-debug-asset-section')
            ->html(...$sectionChildren);
    }
}
