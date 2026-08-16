<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

use PHPForge\Debug\Helper\Fqcn;
use yii\helpers\Inflector;

use function count;

/**
 * Derives the Asset panel view model from the captured bundles.
 *
 * The captured rows are already typed, so this class only computes presentation data: the FQCN split, the anchor id,
 * the per-bundle counters, and the layout hints the card renderer consumes.
 */
final class AssetBundleNormalizer
{
    /**
     * Builds the typed summary rendered by the Asset panel detail view.
     *
     * @param list<AssetBundleRow> $bundles Captured bundles in registration order.
     */
    public function normalize(array $bundles): AssetSummary
    {
        $views = [];
        $totalCss = 0;
        $totalJs = 0;
        $totalDeps = 0;

        foreach ($bundles as $bundle) {
            $cssCount = count($bundle->css);
            $jsCount = count($bundle->js);
            $depsCount = count($bundle->depends);

            $hasFiles = $cssCount + $jsCount > 0;
            $hasWiring = $bundle->sourcePath !== '' || $bundle->basePath !== '' || $bundle->baseUrl !== '';
            $hasDepends = $depsCount > 0;

            $views[] = new AssetBundleView(
                name: $bundle->name,
                shortName: Fqcn::shortName($bundle->name),
                namespace: Fqcn::namespacePart($bundle->name),
                id: Inflector::camel2id($bundle->name),
                sourcePath: $bundle->sourcePath,
                basePath: $bundle->basePath,
                baseUrl: $bundle->baseUrl,
                css: $bundle->css,
                js: $bundle->js,
                depends: $bundle->depends,
                cssCount: $cssCount,
                jsCount: $jsCount,
                depsCount: $depsCount,
                hasFiles: $hasFiles,
                hasWiring: $hasWiring,
                hasDepends: $hasDepends,
                bodyCols: ($hasFiles && ($hasWiring || $hasDepends)) ? 2 : 1,
            );

            $totalCss += $cssCount;
            $totalJs += $jsCount;
            $totalDeps += $depsCount;
        }

        return new AssetSummary(
            bundles: $views,
            totalBundles: count($views),
            totalCss: $totalCss,
            totalJs: $totalJs,
            totalDeps: $totalDeps,
        );
    }
}
