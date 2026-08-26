<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot};
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\ToolbarAsset;
use yii\web\{AssetBundle, AssetManager};

use function is_array;
use function is_string;

/**
 * Captures the asset bundles registered on the request for the Asset Bundles panel.
 */
class AssetCollector extends Collector
{
    /**
     * Captures every application asset bundle registered during the request into the snapshot consumed by the detail
     * view. The debug toolbar's own bundle is excluded because it is infrastructure rather than application content.
     *
     * @return AssetSnapshot|null Captured bundle payload; `null` when the collector never started or the application
     * exposes no `assetManager` component.
     */
    public function capture(): AssetSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        try {
            $assetManager = Yii::$app->get('assetManager');
        } catch (InvalidConfigException) {
            return null;
        }

        if (!$assetManager instanceof AssetManager) {
            return null;
        }

        $bundles = $assetManager->bundles;

        $rows = [];

        if (is_array($bundles)) {
            foreach ($bundles as $name => $bundle) {
                if (!is_string($name) || $name === ToolbarAsset::class || !$bundle instanceof AssetBundle) {
                    continue;
                }

                $rows[] = AssetBundleRow::fromBundle($name, $this->serializeBundle($bundle));
            }
        }

        return new AssetSnapshot($rows, null);
    }

    /**
     * Returns the stable ID pairing this collector with the Asset Bundles panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'asset';
    }

    /**
     * Snapshots the bundle properties consumed by the detail view (paths, files, dependencies).
     *
     * @return array{
     *   basePath: string|null,
     *   baseUrl: string|null,
     *   css: array<array-key, string|array<array-key, mixed>>,
     *   depends: array<class-string>,
     *   js: array<array-key, string|array<array-key, mixed>>,
     *   sourcePath: string|null,
     * } Bundle properties keyed by name.
     */
    private function serializeBundle(AssetBundle $bundle): array
    {
        return [
            'basePath' => $bundle->basePath,
            'baseUrl' => $bundle->baseUrl,
            'css' => $bundle->css,
            'depends' => $bundle->depends,
            'js' => $bundle->js,
            'sourcePath' => $bundle->sourcePath,
        ];
    }
}
