<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

/**
 * Typed aggregate view-model for the Asset Bundles detail view.
 *
 * Bundles the per-request statistics (totals across every registered bundle) with the per-bundle view-models that the
 * detail view iterates over.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class AssetSummary
{
    public function __construct(
        /**
         * @var list<AssetBundleView> Per-bundle view-models in registration order.
         */
        public array $bundles,
        /**
         * Number of bundles in `$bundles`.
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

    public function isEmpty(): bool
    {
        return $this->bundles === [];
    }
}
