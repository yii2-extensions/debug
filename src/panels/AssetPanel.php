<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetPanel as AssetPresenter, AssetSnapshot};
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderer, PanelTitle};
use PHPForge\Debug\Storage\HydrationException;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\web\AssetManager;

use function count;

/**
 * Renders the asset bundles captured by the Asset Bundles collector.
 *
 * Delegates the detail view to the framework-neutral {@see AssetPresenter}; data acquisition lives in
 * {@see \yii\debug\collectors\AssetCollector}.
 */
class AssetPanel extends Panel
{
    /**
     * Captured payload hydrated by {@see hydrate()}, or `null` before hydration.
     */
    private AssetSnapshot|null $snapshot = null;

    /**
     * Renders the detail view through the shared declarative presenter.
     *
     * @return string Rendered panel markup.
     */
    #[Override]
    public function getDetail(): string
    {
        $snapshot = $this->snapshot ?? new AssetSnapshot([], null);

        return PanelRenderer::render(
            $this->getName(),
            (new AssetPresenter())->present($snapshot->jsonSerialize()),
        );
    }

    /**
     * Returns the panel display name from the shared title enum.
     *
     * @return string Panel display name.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::ASSETS->value;
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     *
     * @return string Toolbar icon key.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::ASSETS->value;
    }

    /**
     * Returns whether the loaded capture contains asset bundles or embedded Vite data.
     *
     * @return bool `true` when the capture holds at least one bundle; `false` otherwise.
     */
    #[Override]
    public function hasContent(): bool
    {
        return $this->getBundles() !== [] || $this->snapshot?->vite() !== null;
    }

    /**
     * Decodes the captured payload into the typed asset-bundle snapshot backing this panel.
     *
     * @param array<string, mixed> $payload Captured panel payload.
     *
     * @throws HydrationException when the payload does not match the snapshot schema.
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = AssetSnapshot::fromArray(
            $payload,
            "$.panels.{$this->id}",
        );
    }

    /**
     * Returns whether the application exposes an `assetManager` component the panel can read.
     *
     * @return bool `true` when the panel may capture and render; `false` otherwise.
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

    /**
     * @return list<AssetBundleRow> Registered bundles in registration order.
     */
    private function getBundles(): array
    {
        return $this->snapshot?->bundles() ?? [];
    }
}
