<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetPanel as AssetPresenter, AssetSnapshot};
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderer, PanelTitle};
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
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::ASSETS->value;
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::ASSETS->value;
    }

    /**
     * Returns whether the loaded capture contains asset bundles or embedded Vite data.
     */
    #[Override]
    public function hasContent(): bool
    {
        return $this->getBundles() !== [] || $this->snapshot?->vite() !== null;
    }

    /**
     * @param array<string, mixed> $payload
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
