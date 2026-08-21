<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Asset\AssetSnapshot;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\collectors\AssetCollector;
use yii\debug\tests\support\TestCase;
use yii\helpers\ArrayHelper;
use yii\inertia\Vite;
use yii\web\AssetBundle;

use function dirname;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function unlink;

/**
 * Unit tests for {@see AssetCollector} covering the bundle serialization, the Vite bridge discovery and manifest
 * narrowing, and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('asset')]
final class AssetCollectorTest extends TestCase
{
    public function testCaptureCapturesViteComponentDeclaredWithClassAliasKey(): void
    {
        $collector = $this->makeCollector(
            ['inertiaVue' => ['__class' => Vite::class]],
        );

        self::assertNotNull(
            $this->captureSnapshot($collector)->vite(),
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
                    'resources/js/secondary.js' => ['file' => 'assets/secondary.js'],
                ],
            ),
        );

        $collector = $this->makeCollector(
            [
                'inertiaVue' => [
                    'class' => Vite::class,
                    'baseUrl' => '/build',
                    'devMode' => true,
                    'devServerUrl' => 'http://localhost:5173',
                    'entrypoints' => ['resources/js/app.js'],
                    'manifestPath' => $manifestPath,
                ],
            ],
        );

        $vite = $this->captureSnapshot($collector)->vite();

        self::assertNotNull(
            $vite,
            'Vite snapshot must be captured.',
        );
        self::assertSame(
            '/build',
            $vite->baseUrl,
            'Base URL must be captured.',
        );
        self::assertTrue(
            $vite->devMode,
            'Dev mode flag must be captured.',
        );
        self::assertSame(
            'http://localhost:5173',
            $vite->devServerUrl,
            'Dev server URL must be captured.',
        );
        self::assertSame(
            $manifestPath,
            $vite->manifestPath,
            'Manifest path must be captured.',
        );
        self::assertCount(
            2,
            $vite->chunks,
            'Vite must capture all chunks.',
        );

        $entry = $vite->chunks[0];

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
            $entry->cssCount,
            'CSS count must be captured.',
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

        $secondary = $vite->chunks[1];

        self::assertSame(
            'resources/js/secondary.js',
            $secondary->name,
            'Manifest chunk must keep its source name.',
        );
        self::assertSame(
            'assets/secondary.js',
            $secondary->file,
            'Manifest chunk output file must be captured.',
        );
        self::assertSame(
            0,
            $secondary->cssCount,
            'CSS count must be captured.',
        );
        self::assertSame(
            0,
            $secondary->imports,
            'Import count must be captured.',
        );
        self::assertFalse(
            $secondary->isEntry,
            'Entry flag must be captured.',
        );

        @unlink($manifestPath);
    }

    public function testCaptureIgnoresClosureComponentDefinitions(): void
    {
        $collector = $this->makeCollector(
            ['factory' => static fn(): stdClass => new stdClass()],
        );

        self::assertArrayNotHasKey(
            '@vite',
            $this->captureSnapshot($collector)->bundles(),
            'Unrelated closure component definitions must be ignored.',
        );
    }

    public function testCaptureIgnoresInvalidViteComponentDefinition(): void
    {
        $collector = $this->makeCollector(
            [
                'invalidVite' => Vite::class,
                'validVite' => new Vite(['baseUrl' => '/valid']),
            ],
        );

        Yii::$container->set(
            Vite::class,
            static fn(): never => throw new InvalidConfigException('Invalid Vite fixture.'),
        );

        try {
            $vite = $this->captureSnapshot($collector)->vite();

            self::assertNotNull(
                $vite,
                'Vite instance must be captured.',
            );
            self::assertSame(
                '/valid',
                $vite->baseUrl,
                'Discovery must continue after an invalid Vite definition.',
            );
        } finally {
            Yii::$container->clear(Vite::class);
        }
    }

    public function testCaptureLeavesTheManifestPathEmptyWhenTheBridgeDeclaresNone(): void
    {
        $collector = $this->makeCollector(
            ['inertiaVue' => ['class' => Vite::class]],
        );

        $vite = $this->captureSnapshot($collector)->vite();

        self::assertNotNull(
            $vite,
            'The Vite bridge must still be captured.',
        );
        self::assertSame(
            '',
            $vite->manifestPath,
            'A bridge without a manifest path reports an empty path.',
        );
        self::assertSame(
            [],
            $vite->chunks,
            'No manifest means no chunks.',
        );
    }

    public function testCaptureOmitsViteKeyWithoutBridgeComponent(): void
    {
        $collector = $this->makeCollector();

        self::assertArrayNotHasKey(
            '@vite',
            $this->captureSnapshot($collector)->bundles(),
            'No bridge component must mean no reserved key.',
        );
    }

    public function testCaptureResolvesPrebuiltViteComponent(): void
    {
        $collector = $this->makeCollector(
            ['inertiaVue' => new Vite(['baseUrl' => '/prebuilt'])],
        );

        $vite = $this->captureSnapshot($collector)->vite();

        self::assertNotNull(
            $vite,
            'A prebuilt Vite component must be discovered.',
        );
        self::assertSame(
            '/prebuilt',
            $vite->baseUrl,
            'The base URL of the prebuilt Vite component must be correct.',
        );
    }

    public function testCaptureResolvesViteComponentDeclaredAsClassString(): void
    {
        $collector = $this->makeCollector(
            ['inertiaVue' => Vite::class],
        );

        self::assertNotNull(
            $this->captureSnapshot($collector)->vite(),
            'A Vite component declared as a class string must be discovered.',
        );
    }

    public function testCaptureReturnsEmptyArrayWhenNoBundlesRegistered(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->getAssetManager()->bundles = [];

        self::assertSame(
            [],
            $this->captureSnapshot($collector)->bundles(),
            'No registered bundles must yield an empty snapshot.',
        );
    }

    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new AssetCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullWhenAssetManagerComponentIsMissing(): void
    {
        $this->mockWebApplication();

        Yii::$app->setComponents(['assetManager' => null]);

        $collector = new AssetCollector();

        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'Missing asset manager must collapse to `null`.',
        );
    }

    public function testCaptureSerializesRegisteredBundleAndReplacesClosureCallbacks(): void
    {
        $collector = $this->makeCollector();

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

        $snapshot = $this->captureSnapshot($collector)->bundles();

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

    public function testCaptureSkipsManifestEntriesThatAreNotNamedChunkDescriptors(): void
    {
        $manifestPath = dirname(__DIR__, 2) . '/runtime/vite-manifest-malformed.json';

        @mkdir(dirname($manifestPath), 0o777, true);
        file_put_contents(
            $manifestPath,
            // A numeric key decodes to an `int`, and a scalar value is not a chunk descriptor; both must be skipped.
            '{"0":{"file":"assets/numeric.js"},"broken.js":"not-a-descriptor","resources/js/app.js":{"file":"a.js"}}',
        );

        $collector = $this->makeCollector(
            [
                'inertiaVue' => [
                    'class' => Vite::class,
                    'manifestPath' => $manifestPath,
                ],
            ],
        );

        try {
            $vite = $this->captureSnapshot($collector)->vite();

            self::assertNotNull(
                $vite,
                'The Vite bridge must still be captured.',
            );
            self::assertCount(
                1,
                $vite->chunks,
                'Only the well-formed named descriptor survives.',
            );
            self::assertSame(
                'resources/js/app.js',
                $vite->chunks[0]->name,
                'The surviving chunk keeps its source name.',
            );
        } finally {
            @unlink($manifestPath);
        }
    }

    public function testCaptureSkipsNonStringKeysAndNonAssetBundleEntries(): void
    {
        $collector = $this->makeCollector();

        $bundle = new AssetBundle();

        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];

        Yii::$app->getAssetManager()->bundles = [
            0 => $bundle,                 // non-string key, must be skipped
            'invalid' => new stdClass(), // non-AssetBundle value, must be skipped
            'debug' => $bundle,
        ];

        $snapshot = $this->captureSnapshot($collector)->bundles();

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

        $collector = $this->makeCollector(
            [
                'inertiaVue' => [
                    'class' => Vite::class,
                    'manifestPath' => $manifestPath,
                ],
            ],
        );

        try {
            $vite = $this->captureSnapshot($collector)->vite();

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

    public function testIdPairsWithTheAssetBundlesPanel(): void
    {
        self::assertSame(
            'asset',
            (new AssetCollector())->id(),
            "Stable ID must be 'asset'.",
        );
    }

    /**
     * Captures the asset snapshot, failing when the started collector produces nothing.
     *
     * @param AssetCollector $collector Started collector.
     *
     * @return AssetSnapshot Captured asset snapshot.
     */
    private function captureSnapshot(AssetCollector $collector): AssetSnapshot
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot;
    }

    /**
     * Creates a started collector on top of a mocked web application with a writable asset manager.
     *
     * @param array<string, mixed> $components Extra application components.
     *
     * @return AssetCollector Started collector.
     */
    private function makeCollector(array $components = []): AssetCollector
    {
        $assetPath = dirname(__DIR__, 2) . '/runtime/assets';

        @mkdir($assetPath, 0o777, true);

        $this->mockWebApplication(
            [
                'components' => ArrayHelper::merge(
                    [
                        'assetManager' => [
                            'basePath' => $assetPath,
                            'baseUrl' => '/assets',
                        ],
                    ],
                    $components,
                ),
            ],
        );

        $collector = new AssetCollector();

        $collector->startup();

        return $collector;
    }
}
