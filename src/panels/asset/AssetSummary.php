<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

/**
 * Typed aggregate view-model for the Asset Bundles detail view.
 *
 * Bundles the per-request totals (across every registered bundle) with the per-bundle view-models that the detail view
 * iterates over.
 */
final readonly class AssetSummary
{
    public function __construct(
        /**
         * @var list<AssetBundleView> Per-bundle view-models in registration order.
         */
        public array $bundles,
        /**
         * Number of bundles in {@see $bundles}.
         */
        public int $totalBundles,
        /**
         * Sum of CSS files across every bundle.
         */
        public int $totalCss,
        /**
         * Sum of JS files across every bundle.
         */
        public int $totalJs,
        /**
         * Sum of declared dependencies across every bundle.
         */
        public int $totalDeps,
    ) {}

    /**
     * Returns whether the summary carries no bundles.
     */
    public function isEmpty(): bool
    {
        return $this->bundles === [];
    }
}
