<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Asset\AssetSnapshot;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\debug\collectors\AssetCollector;
use yii\debug\tests\support\TestCase;
use yii\debug\ToolbarAsset;
use yii\web\AssetBundle;

use function dirname;
use function mkdir;

/**
 * Unit tests for {@see AssetCollector} covering bundle serialization, toolbar-infrastructure filtering, and the
 * startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('asset')]
final class AssetCollectorTest extends TestCase
{
    public function testCaptureReturnsEmptySnapshotWhenNoBundlesRegistered(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->getAssetManager()->bundles = [];

        $snapshot = $this->captureSnapshot($collector);

        self::assertSame(
            [],
            $snapshot->bundles(),
            'No registered bundles must yield an empty snapshot.',
        );
        self::assertNull(
            $snapshot->vite(),
            'The compatibility Vite field must remain null.',
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

    public function testCaptureReturnsNullWhenAssetManagerHasWrongType(): void
    {
        $this->mockWebApplication(
            ['components' => ['assetManager' => new stdClass()]],
        );

        $collector = new AssetCollector();

        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'A non-asset-manager application component must yield no snapshot.',
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

    public function testCaptureSkipsToolbarBundleAndPreservesApplicationBundles(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->getAssetManager()->bundles = [
            ToolbarAsset::class => new ToolbarAsset(),
            'application' => new AssetBundle(['baseUrl' => '/assets/application']),
        ];

        $bundles = $this->captureSnapshot($collector)->bundles();

        self::assertCount(
            1,
            $bundles,
            'Only the debugger toolbar bundle must be filtered out.',
        );
        self::assertSame(
            'application',
            $bundles[0]->name,
            'Application asset bundles must remain in the snapshot.',
        );
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
     * @return AssetCollector Started collector.
     */
    private function makeCollector(): AssetCollector
    {
        $assetPath = dirname(__DIR__, 2) . '/runtime/assets';

        @mkdir($assetPath, 0o777, true);

        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'basePath' => $assetPath,
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );

        $collector = new AssetCollector();

        $collector->startup();

        return $collector;
    }
}
