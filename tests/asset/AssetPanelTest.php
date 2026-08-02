<?php

declare(strict_types=1);

namespace yii\debug\tests\asset;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\{DebugAsset, LogTarget, Module};
use yii\debug\panels\asset\{AssetBundleRow, AssetSnapshot, ViteChunk, ViteManifest};
use yii\debug\panels\AssetPanel;
use yii\debug\tests\support\TestCase;
use yii\inertia\Vite;
use yii\web\AssetBundle;

use function count;
use function dirname;
use function file_put_contents;
use function is_array;
use function is_int;
use function is_string;
use function mkdir;
use function unlink;

/**
 * Unit tests for {@see AssetPanel} covering `getName`/`getToolbarIcon`, the toolbar-items chip with bundle count
 * (and the `null` short-circuit when no bundles), `getDetail` rendering, `isEnabled` resolution, the `capture()`
 * snapshot path (including the `format()` URL wrapping, `formatOptions()` stringification, and the
 * `serializeOptions()` closure-to-label substitution).
 */
#[Group('asset')]
#[Group('panel')]
final class AssetPanelTest extends TestCase
{
    public function testCaptureCapturesViteComponentDeclaredWithClassAliasKey(): void
    {
        $panel = $this->makePanel(AssetPanel::class, ['inertiaVue' => ['__class' => Vite::class]]);

        self::assertNotNull(
            $panel->capture()->vite(),
            "A Vite component declared with '__class' must be discovered.",
        );
    }

    public function testCaptureCapturesViteManifestWhenBridgeIsRegistered(): void
    {
        $manifestPath = dirname(__DIR__, 2) . '/runtime/vite-manifest-test.json';

        @mkdir(dirname($manifestPath), 0o777, true);

        file_put_contents(
            $manifestPath,
            json_encode(
                [
                    'resources/js/app.js' => [
                        'css' => ['assets/app-abc.css'],
                        'file' => 'assets/app-def.js',
                        'imports' => ['_shared-xyz.js'],
                        'isEntry' => true,
                    ],
                ],
            ),
        );

        $panel = $this->makePanel(
            AssetPanel::class,
            [
                'inertiaVue' => [
                    'class' => Vite::class,
                    'entrypoints' => ['resources/js/app.js'],
                    'manifestPath' => $manifestPath,
                ],
            ],
        );

        $vite = $panel->capture()->vite();

        self::assertNotNull(
            $vite,
            'Vite snapshot must be captured.',
        );

        $entry = $vite->chunks[0] ?? self::fail('Manifest chunk must be captured.');

        self::assertSame(
            'resources/js/app.js',
            $entry->name,
            'Manifest chunk must keep its source name.',
        );
        self::assertSame(
            'assets/app-def.js',
            $entry->file,
            'Manifest chunk output file must be captured.',
        );
        self::assertSame(
            1,
            $entry->imports,
            'Import count must be captured.',
        );
        self::assertTrue(
            $entry->isEntry,
            'Entry flag must be captured.',
        );

        @unlink($manifestPath);
    }

    public function testCaptureIgnoresClosureComponentDefinitions(): void
    {
        $panel = $this->makePanel(
            AssetPanel::class,
            ['factory' => static fn(): stdClass => new stdClass()],
        );

        self::assertArrayNotHasKey(
            '@vite',
            $panel->capture()->bundles(),
            'Unrelated closure component definitions must be ignored.',
        );
    }

    public function testCaptureIgnoresInvalidViteComponentDefinition(): void
    {
        $panel = $this->makePanel(AssetPanel::class, ['inertiaVue' => Vite::class]);

        Yii::$container->set(
            Vite::class,
            static fn(): never => throw new InvalidConfigException('Invalid Vite fixture.'),
        );

        try {
            self::assertArrayNotHasKey(
                '@vite',
                $panel->capture()->bundles(),
                'An invalid Vite component definition must be ignored.',
            );
        } finally {
            Yii::$container->clear(Vite::class);
        }
    }

    public function testCaptureOmitsViteKeyWithoutBridgeComponent(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        self::assertArrayNotHasKey(
            '@vite',
            $panel->capture()->bundles(),
            'No bridge component must mean no reserved key.',
        );
    }

    public function testCaptureReturnsEmptyArrayWhenNoBundlesRegistered(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        Yii::$app->getAssetManager()->bundles = [];

        self::assertSame(
            [],
            $panel->capture()->bundles(),
            'No registered bundles must yield an empty snapshot.',
        );
    }

    public function testCaptureSerializesRegisteredBundleAndReplacesClosureCallbacks(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $bundle = new AssetBundle();

        $bundle->basePath = '/tmp/base';
        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];
        $bundle->js = ['script.js'];
        $bundle->sourcePath = '/src/assets';
        $bundle->publishOptions = [
            'beforeCopy' => static fn(): bool => true,
            'afterCopy' => static fn(): bool => true,
            'forceCopy' => true,
        ];

        Yii::$app->getAssetManager()->bundles = ['debug' => $bundle];

        $snapshot = $panel->capture()->bundles();

        $captured = $snapshot[0] ?? self::fail('Snapshot must include the registered bundle.');

        self::assertSame(
            'debug',
            $captured->name,
            'Bundle name must round-trip.',
        );
        self::assertSame(
            '/assets/debug',
            $captured->baseUrl,
            'Base URL must round-trip.',
        );
        self::assertSame(
            ['style.css'],
            $captured->css,
            'CSS files must round-trip.',
        );
        self::assertSame(
            ['script.js'],
            $captured->js,
            'JS files must round-trip.',
        );
        self::assertSame(
            '/src/assets',
            $captured->sourcePath,
            'Source path must round-trip.',
        );
    }

    public function testCaptureSkipsNonStringKeysAndNonAssetBundleEntries(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $bundle = new AssetBundle();

        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];

        Yii::$app->getAssetManager()->bundles = [
            'debug' => $bundle,
            0 => $bundle,                 // non-string key, must be skipped
            'invalid' => new stdClass(), // non-AssetBundle value, must be skipped
        ];

        $snapshot = $panel->capture()->bundles();

        self::assertCount(
            1,
            $snapshot,
            'Only the valid string-keyed bundle survives.',
        );
        self::assertSame(
            'debug',
            $snapshot[0]->name,
            'Numeric keys and non-AssetBundle values must be filtered out.',
        );
    }

    public function testCaptureTreatsMalformedViteManifestAsEmpty(): void
    {
        $manifestPath = dirname(__DIR__, 2) . '/runtime/vite-manifest-invalid.json';

        file_put_contents($manifestPath, '{not-json');

        $panel = $this->makePanel(
            AssetPanel::class,
            ['inertiaVue' => ['class' => Vite::class, 'manifestPath' => $manifestPath]],
        );

        try {
            $vite = $panel->capture()->vite();

            self::assertNotNull(
                $vite,
                'The Vite bridge must still be captured.',
            );
            self::assertSame(
                [],
                $vite->chunks,
                'Malformed Vite manifests must produce an empty chunk list.',
            );
        } finally {
            @unlink($manifestPath);
        }
    }

    public function testFormatOptionsStringifiesScalarsAndDebugTypesOtherValues(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $formatted = $this->invoke(
            $panel,
            'formatOptions',
            [['flag' => true, 'callback' => static fn(): bool => true, 'name' => 'debug']],
        );

        self::assertIsArray(
            $formatted,
            'formatOptions() must return an array.',
        );

        $flag = $formatted['flag'] ?? '';
        $callback = $formatted['callback'] ?? '';
        $name = $formatted['name'] ?? '';

        self::assertIsString(
            $flag,
            'Flag entry must be stringified.',
        );
        self::assertIsString(
            $callback,
            'Callback entry must be stringified.',
        );
        self::assertIsString(
            $name,
            'Name entry must be stringified.',
        );
        self::assertStringContainsString(
            '1',
            $flag,
            "Boolean 'true' must surface as '1'.",
        );
        self::assertStringContainsString(
            'Closure',
            $callback,
            "Non-scalar values must surface via 'get_debug_type()'.",
        );
        self::assertStringContainsString(
            'debug',
            $name,
            'String values must round-trip verbatim.',
        );
    }

    public function testFormatWrapsCssAndJsFilesInAnchorsBoundToBaseUrl(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $bundle = new AssetBundle();

        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];
        $bundle->js = ['script.js'];

        $formatted = $this->invoke(
            $panel,
            'format',
            [['debug' => $bundle]],
        );

        self::assertIsArray(
            $formatted,
            "'format()' must return an array.",
        );
        self::assertInstanceOf(
            AssetBundle::class,
            $formatted['debug'] ?? null,
            'Bundle must round-trip.',
        );

        $css = $formatted['debug']->css[0] ?? '';
        $js = $formatted['debug']->js[0] ?? '';

        self::assertIsString(
            $css,
            'CSS entry must be stringified.',
        );
        self::assertIsString(
            $js,
            'JS entry must be stringified.',
        );
        self::assertStringContainsString(
            'href="/assets/debug/style.css"',
            $css,
            'CSS link must point at baseUrl + file.',
        );
        self::assertStringContainsString(
            'href="/assets/debug/script.js"',
            $js,
            'JS link must point at baseUrl + file.',
        );
    }

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
