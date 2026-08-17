<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Asset\{AssetBundleNormalizer, AssetBundleRow, AssetSnapshot};
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\web\AssetManager;

use function count;

/**
 * Renders the asset bundles captured by the Asset Bundles collector.
 *
 * Presents each bundle's source path, base path, base URL, CSS/JS files, and dependency tree from the static
 * snapshot; data acquisition lives in {@see \yii\debug\collectors\AssetCollector}.
 */
class AssetPanel extends Panel
{
    protected const string ICON = 'asset';
    protected const string NAME = 'Asset Bundles';

    private AssetSnapshot|null $snapshot = null;

    /**
     * @return list<AssetBundleRow> Registered bundles in registration order.
     */
    public function getBundles(): array
    {
        return $this->snapshot?->bundles() ?? [];
    }

    /**
     * Renders the detail view from the normalized bundle summary and the optional Vite manifest snapshot.
     */
    #[Override]
    public function getDetail(): string
    {
        return Yii::$app->view->render(
            'panels/assets/detail',
            [
                'summary' => (new AssetBundleNormalizer())->normalize($this->getBundles()),
                'vite' => $this->snapshot?->vite(),
            ],
            $this,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = AssetSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns whether the application exposes an `assetManager` component the panel can read.
     */
    #[Override]
    public function isEnabled(): bool
    {
        try {
            return Yii::$app->get('assetManager') instanceof AssetManager;
        } catch (InvalidConfigException) {
            return false;
        }
    }

    /**
     * Returns the toolbar item showing the count of registered bundles, or `[]` when none were captured.
     *
     * @return array<int, array<string, mixed>> Single-element list with the `info` chip, or `[]`.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $bundles = $this->getBundles();

        if ($bundles === []) {
            return [];
        }

        return [
            [
                'status' => 'info',
                'title' => 'Number of asset bundles loaded',
                'value' => count($bundles),
            ],
        ];
    }
}
