<?php

declare(strict_types=1);

namespace yii\debug\tests\asset;

use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot, ViteChunk, ViteManifest};
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{DebugAsset, LogTarget, Module};
use yii\debug\panels\AssetPanel;
use yii\debug\tests\support\TestCase;

use function count;
use function is_array;
use function is_int;
use function is_string;

/**
 * Unit tests for {@see AssetPanel} covering `getName`/`getToolbarIcon`, the toolbar-items chip with bundle count
 * (and the `null` short-circuit when no bundles), `getDetail` rendering, and `isEnabled` resolution.
 */
#[Group('asset')]
#[Group('panel')]
final class AssetPanelTest extends TestCase
{
    public function testGetDetailRendersBundleSummary(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot(
                [
                    DebugAsset::class => [
                        'basePath' => '/tmp',
                        'baseUrl' => '/assets/debug',
                        'css' => [],
                        'cssOptions' => [],
                        'depends' => [],
                        'js' => ['debug.min.js'],
                        'jsOptions' => [],
                        'publishOptions' => [],
                        'sourcePath' => '/src/assets',
                    ],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'DebugAsset',
            $html,
            'Detail view must surface the bundle FQCN.',
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoBundlesAreLoaded(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot([]),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $html,
            'Empty data must render the empty-state container.',
        );
        self::assertStringContainsString(
            'No asset bundles loaded',
            $html,
            'Empty state must surface the headline.',
        );
        self::assertStringContainsString(
            'yii-debug-asset-stats',
            $html,
            'Stats strip must render alongside the card.',
        );
    }

    public function testGetDetailRendersViteDevServerWithoutManifestEntries(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot(
                [
                    '@vite' => [
                        'baseUrl' => '@web/build',
                        'devMode' => true,
                        'devServerUrl' => 'http://localhost:5173',
                        'entries' => [],
                        'entrypoints' => [],
                        'manifestPath' => '',
                    ],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Dev server (http://localhost:5173)',
            $html,
            'Development mode must surface the configured Vite server.',
        );
        self::assertStringNotContainsString(
            'The Vite manifest is missing or empty',
            $html,
            'Development mode must not request a build manifest.',
        );
    }

    public function testGetDetailRendersViteSectionAlongsideEmptyBundles(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot(
                [
                    '@vite' => [
                        'baseUrl' => '@web/build',
                        'devMode' => false,
                        'devServerUrl' => null,
                        'entries' => [
                            'resources/js/app.js' => [
                                'css' => ['assets/app-abc.css'],
                                'file' => 'assets/app-def.js',
                                'imports' => 2,
                                'isEntry' => true,
                            ],
                            'resources/js/admin.js' => [
                                'css' => [],
                                'file' => 'assets/admin-def.js',
                                'imports' => 0,
                                'isEntry' => false,
                            ],
                        ],
                        'entrypoints' => ['resources/js/app.js'],
                        'manifestPath' => '/tmp/manifest.json',
                    ],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Vite',
            $html,
            'Vite section heading must be present.',
        );
        self::assertStringContainsString(
            'resources/js/app.js',
            $html,
            'Chunk name must surface in the grid.',
        );
        self::assertStringContainsString(
            'assets/app-def.js',
            $html,
            'Output file must surface in the grid.',
        );
        self::assertStringContainsString(
            'No asset bundles loaded',
            $html,
            'Bundle empty state must still render.',
        );
    }

    public function testGetDetailReportsMissingViteBuildManifest(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot(
                [
                    '@vite' => [
                        'baseUrl' => '',
                        'devMode' => false,
                        'devServerUrl' => null,
                        'entries' => [],
                        'entrypoints' => [],
                        'manifestPath' => '/tmp/missing-manifest.json',
                    ],
                ],
            ),
        );

        self::assertStringContainsString(
            'The Vite manifest is missing or empty',
            $panel->getDetail(),
            'Build mode without chunks must explain how to populate the manifest.',
        );
    }

    public function testGetNameAndIconReturnConstantsForToolbar(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        self::assertSame(
            'Asset Bundles',
            $panel->getName(),
            "Panel name must be 'Asset Bundles'.",
        );
        self::assertSame(
            'asset',
            $panel->getToolbarIcon(),
            "Toolbar icon must be 'asset'.",
        );
    }

    public function testGetToolbarItemsEmitsInfoChipWithBundleCount(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot(
                [
                    'BundleA' => [],
                    'BundleB' => [],
                ],
            ),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must surface as a list.',
        );
        self::assertIsArray(
            $items[0] ?? null,
            'First chip must be an array.',
        );
        self::assertSame(
            2,
            $items[0]['value'] ?? null,
            "Chip 'value' must match the bundle count.",
        );
        self::assertSame(
            'info',
            $items[0]['status'] ?? null,
            "Chip 'status' must be 'info'.",
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenNoBundles(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $this->hydratePanel(
            $panel,
            self::assetSnapshot([]),
        );

        self::assertSame(
            [],
            $this->invoke($panel, 'getToolbarItems'),
            'Empty bundle list must yield no toolbar chip.',
        );
    }

    public function testIsEnabledFalseWhenAssetManagerComponentIsMissing(): void
    {
        $this->mockWebApplication();

        Yii::$app->setComponents(['assetManager' => null]);

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $panel = new AssetPanel();

        $panel->module = $module;

        self::assertFalse(
            $panel->isEnabled(),
            "Missing 'assetManager' component must collapse 'isEnabled()' to 'false'.",
        );
    }

    public function testIsEnabledTrueWhenAssetManagerResolves(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        self::assertTrue(
            $panel->isEnabled(),
            'Default mocked app must expose an asset manager.',
        );
    }

    /**
     * Builds a snapshot from the legacy bundle-map fixture shape, splitting out the reserved Vite entry.
     *
     * @param array<array-key, mixed> $map Bundle map, optionally carrying a `@vite` entry.
     */
    private static function assetSnapshot(array $map): AssetSnapshot
    {
        $vite = $map['@vite'] ?? null;

        unset($map['@vite']);

        $rows = [];

        foreach ($map as $name => $bundle) {
            if (is_string($name) && is_array($bundle)) {
                $rows[] = AssetBundleRow::fromBundle($name, $bundle);
            }
        }

        return new AssetSnapshot($rows, is_array($vite) ? self::viteManifest($vite) : null);
    }

    /**
     * @param array<array-key, mixed> $vite Legacy Vite fixture shape.
     */
    private static function viteManifest(array $vite): ViteManifest
    {
        $chunks = [];
        $entries = $vite['entries'] ?? null;

        foreach (is_array($entries) ? $entries : [] as $name => $chunk) {
            if (!is_string($name) || !is_array($chunk)) {
                continue;
            }

            $chunks[] = new ViteChunk(
                name: $name,
                file: is_string($chunk['file'] ?? null) ? $chunk['file'] : '',
                cssCount: is_array($chunk['css'] ?? null) ? count($chunk['css']) : 0,
                imports: is_int($chunk['imports'] ?? null) ? $chunk['imports'] : 0,
                isEntry: ($chunk['isEntry'] ?? false) === true,
            );
        }

        return new ViteManifest(
            baseUrl: is_string($vite['baseUrl'] ?? null) ? $vite['baseUrl'] : '',
            devMode: ($vite['devMode'] ?? false) === true,
            devServerUrl: is_string($vite['devServerUrl'] ?? null) ? $vite['devServerUrl'] : null,
            manifestPath: is_string($vite['manifestPath'] ?? null) ? $vite['manifestPath'] : '',
            chunks: $chunks,
        );
    }
}
