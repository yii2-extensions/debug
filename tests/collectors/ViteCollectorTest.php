<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Vite\Configuration\{DevelopmentConfiguration, ProductionConfiguration};
use PHPForge\Vite\Vite;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use stdClass;
use Yii;
use yii\debug\collectors\ViteCollector;
use yii\debug\tests\support\TestCase;
use yii\inertia\Vite as LegacyVite;

use function dirname;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function unlink;

/**
 * Unit tests for {@see ViteCollector} loaded-service discovery and configuration-safe capture.
 */
#[Group('collector')]
#[Group('vite')]
final class ViteCollectorTest extends TestCase
{
    public function testCaptureCapturesCanonicalModernDevelopmentConfiguration(): void
    {
        $collector = $this->makeCollector(
            [
                'frontend' => [
                    'class' => Vite::class,
                    '__construct()' => [
                        'configuration' => new DevelopmentConfiguration(
                            'http://localhost:5173',
                            false,
                        ),
                        'entrypoints' => ['resources/js/app.js'],
                    ],
                ],
            ],
            ['frontend'],
        );

        $component = self::componentAt($this->captureSnapshot($collector), 0);

        self::assertSame(
            'frontend',
            $component->id,
            'The Yii component ID must be retained.',
        );
        self::assertSame(
            Vite::class,
            $component->class,
            'The loaded implementation class must be retained.',
        );
        self::assertSame(
            ViteComponent::IMPLEMENTATION_MODERN,
            $component->implementation,
            'The framework-neutral facade must be identified as modern.',
        );
        self::assertTrue(
            $component->inspectionAvailable,
            'Canonical constructor configuration must be inspectable.',
        );
        self::assertSame(
            ViteComponent::MODE_DEVELOPMENT,
            $component->mode,
            'Development configuration must select development mode.',
        );
        self::assertSame(
            ['resources/js/app.js'],
            $component->entrypoints,
            'Configured entrypoints must be captured in order.',
        );
        self::assertSame(
            'http://localhost:5173',
            $component->devServerUrl,
            'The development-server URL must be captured.',
        );
        self::assertFalse(
            $component->includeViteClient,
            'The Vite-client flag must be captured without executing asset resolution.',
        );
        self::assertNull(
            $component->modulePreload,
            'Production-only flags must remain unavailable in development.',
        );
        self::assertSame(
            [],
            $component->chunks(),
            'Development configuration must not read a build manifest.',
        );
    }

    public function testCaptureCapturesCanonicalModernProductionManifest(): void
    {
        $manifestPath = dirname(__DIR__, 2) . '/runtime/vite-panel-manifest.json';

        @mkdir(dirname($manifestPath), 0o777, true);
        file_put_contents(
            $manifestPath,
            json_encode(
                [
                    '0' => ['file' => 'assets/numeric.js'],
                    'broken.js' => 'not-a-descriptor',
                    'resources/js/app.js' => [
                        'css' => ['assets/app.css'],
                        'file' => 'assets/app.js',
                        'imports' => ['_shared.js'],
                        'isEntry' => true,
                    ],
                    'resources/js/empty.js' => ['file' => 42],
                ],
            ),
        );

        try {
            $collector = $this->makeCollector(
                [
                    'frontend' => [
                        'class' => Vite::class,
                        '__construct()' => [
                            'configuration' => new ProductionConfiguration($manifestPath, '/build', false),
                            'entrypoints' => ['resources/js/app.js'],
                        ],
                    ],
                ],
                ['frontend'],
            );

            $component = self::componentAt($this->captureSnapshot($collector), 0);

            $chunks = $component->chunks();

            $appChunk = $chunks[0] ?? self::fail('The application chunk must exist.');
            $emptyChunk = $chunks[1] ?? self::fail('The chunk with an invalid file must exist.');

            self::assertSame(
                ViteComponent::MODE_PRODUCTION,
                $component->mode,
                'Production configuration must select production mode.',
            );
            self::assertTrue(
                $component->inspectionAvailable,
                'Canonical production constructor configuration must be inspectable.',
            );
            self::assertSame(
                '/build',
                $component->baseUrl,
                'The production asset base URL must be captured.',
            );
            self::assertSame(
                $manifestPath,
                $component->manifestPath,
                'The absolute manifest path must be retained.',
            );
            self::assertFalse(
                $component->modulePreload,
                'The module-preload flag must be captured.',
            );
            self::assertNull(
                $component->includeViteClient,
                'Development-only flags must remain unavailable in production.',
            );
            self::assertCount(
                2,
                $chunks,
                'Only named object manifest entries must survive.',
            );
            self::assertSame(
                'resources/js/app.js',
                $appChunk->name,
                'The manifest key must name the chunk.',
            );
            self::assertSame(
                'assets/app.js',
                $appChunk->file,
                'The emitted file must be captured.',
            );
            self::assertSame(
                1,
                $appChunk->cssCount,
                'The stylesheet count must be captured.',
            );
            self::assertSame(
                1,
                $appChunk->imports,
                'The static import count must be captured.',
            );
            self::assertTrue(
                $appChunk->isEntry,
                'The entrypoint flag must be captured.',
            );
            self::assertSame(
                '',
                $emptyChunk->file,
                'A non-string optional file must normalize to an empty label.',
            );
            self::assertSame(
                0,
                $emptyChunk->cssCount,
                'A missing stylesheet list must produce a zero count.',
            );
            self::assertSame(
                0,
                $emptyChunk->imports,
                'A missing import list must produce a zero count.',
            );
            self::assertFalse(
                $emptyChunk->isEntry,
                'A missing entrypoint flag must default to false.',
            );
        } finally {
            @unlink($manifestPath);
        }
    }

    public function testCaptureDoesNotInstantiateUnloadedComponentsAndSkipsUnrelatedServices(): void
    {
        $factoryCalls = 0;

        $this->mockWebApplication(
            [
                'components' => [
                    'lazyVite' => static function () use (&$factoryCalls): Vite {
                        $factoryCalls++;

                        return new Vite(new DevelopmentConfiguration('http://localhost:5173'));
                    },
                    'unrelated' => new stdClass(),
                ],
            ],
        );

        Yii::$app->get('unrelated');

        $collector = new ViteCollector();
        $collector->startup();

        self::assertNull(
            $collector->capture(),
            'No already-loaded Vite service must produce no snapshot.',
        );
        self::assertSame(
            0,
            $factoryCalls,
            'Capture must never instantiate a lazy Vite component.',
        );
    }

    public function testCaptureMarksFactoryAndPrebuiltModernComponentsUnavailable(): void
    {
        $configuration = new DevelopmentConfiguration('http://localhost:5173');

        $collector = $this->makeCollector(
            [
                'prebuilt' => new Vite($configuration, ['resources/js/prebuilt.js']),
                'factory' => static fn(): Vite => new Vite($configuration, ['resources/js/factory.js']),
            ],
            ['prebuilt', 'factory'],
        );

        $snapshot = $this->captureSnapshot($collector);

        $components = $snapshot->components();

        $prebuilt = self::componentAt($snapshot, 0);
        $factory = self::componentAt($snapshot, 1);

        self::assertSame(
            ['prebuilt', 'factory'],
            [$prebuilt->id, $factory->id],
            'Every loaded modern component must be preserved in service-locator order.',
        );

        foreach ($components as $component) {
            self::assertFalse(
                $component->inspectionAvailable,
                'Configuration hidden behind an object or factory must be explicitly unavailable.',
            );
            self::assertSame(
                ViteComponent::MODE_UNKNOWN,
                $component->mode,
                'Unavailable configuration must not guess the active mode.',
            );
            self::assertSame(
                [],
                $component->entrypoints,
                'Unavailable private entrypoints must not be guessed.',
            );
        }
    }

    public function testCaptureMarksMalformedCanonicalModernDefinitionsUnavailable(): void
    {
        $configuration = new DevelopmentConfiguration('http://localhost:5173');
        $component = new Vite($configuration);

        $malformedDevelopment = (new ReflectionClass(DevelopmentConfiguration::class))->newInstanceWithoutConstructor();
        $malformedProduction = (new ReflectionClass(ProductionConfiguration::class))->newInstanceWithoutConstructor();

        $definitions = [
            ['class' => Vite::class],
            ['__construct()' => ['configuration' => $configuration, 'entrypoints' => 'app.js']],
            ['__construct()' => ['configuration' => $configuration, 'entrypoints' => [42]]],
            ['__construct()' => ['configuration' => new stdClass(), 'entrypoints' => []]],
            ['__construct()' => ['configuration' => $malformedDevelopment, 'entrypoints' => []]],
            ['__construct()' => ['configuration' => $malformedProduction, 'entrypoints' => []]],
        ];

        foreach ($definitions as $definition) {
            $captured = $this->invokeStatic(
                ViteCollector::class,
                'modernComponent',
                ['frontend', $component, $definition],
            );

            self::assertInstanceOf(
                ViteComponent::class,
                $captured,
                'Malformed constructor metadata must still produce a Vite component.',
            );
            self::assertFalse(
                $captured->inspectionAvailable,
                'Malformed constructor metadata must be reported as unavailable instead of guessed.',
            );
            self::assertSame(
                ViteComponent::MODE_UNKNOWN,
                $captured->mode,
                'Malformed constructor metadata must not imply a Vite mode.',
            );
        }
    }

    public function testCapturePreservesLoadedLegacyDevelopmentAndProductionComponents(): void
    {
        $manifestPath = dirname(__DIR__, 2) . '/runtime/legacy-vite-panel-manifest.json';

        file_put_contents(
            $manifestPath,
            json_encode(['resources/js/legacy.js' => ['file' => 'assets/legacy.js', 'isEntry' => true]]),
        );

        try {
            $collector = $this->makeCollector(
                [
                    'legacyDev' => [
                        'class' => LegacyVite::class,
                        'baseUrl' => '@web/build',
                        'devMode' => true,
                        'devServerUrl' => 'http://localhost:5174',
                        'entrypoints' => ['resources/js/dev.js'],
                        'includeViteClient' => false,
                    ],
                    'legacyBuild' => [
                        'class' => LegacyVite::class,
                        'baseUrl' => '/build',
                        'entrypoints' => ['resources/js/legacy.js'],
                        'manifestPath' => $manifestPath,
                        'modulePreload' => false,
                    ],
                ],
                ['legacyDev', 'legacyBuild'],
            );

            $snapshot = $this->captureSnapshot($collector);

            $development = self::componentAt($snapshot, 0);
            $production = self::componentAt($snapshot, 1);

            self::assertSame(
                ViteComponent::IMPLEMENTATION_LEGACY,
                $development->implementation,
                'The former Yii Vite bridge must remain identifiable.',
            );
            self::assertTrue(
                $development->inspectionAvailable,
                'The legacy component exposes inspectable public configuration.',
            );
            self::assertSame(ViteComponent::MODE_DEVELOPMENT, $development->mode, 'Legacy dev mode must be retained.');
            self::assertSame(
                'http://localhost:5174',
                $development->devServerUrl,
                'Legacy development URL must be retained.',
            );
            self::assertFalse($development->includeViteClient, 'Legacy Vite-client configuration must be retained.');
            self::assertSame(
                ViteComponent::MODE_PRODUCTION,
                $production->mode,
                'Legacy build mode must be retained.',
            );
            self::assertFalse(
                $production->modulePreload,
                'Legacy module-preload configuration must be retained.',
            );
            self::assertCount(
                1,
                $production->chunks(),
                'Legacy production manifests must remain inspectable.',
            );
        } finally {
            @unlink($manifestPath);
        }
    }

    public function testCaptureReturnsEmptyChunksForMissingAndMalformedManifests(): void
    {
        $invalidPath = dirname(__DIR__, 2) . '/runtime/vite-panel-invalid.json';
        $missingPath = dirname(__DIR__, 2) . '/runtime/vite-panel-missing.json';

        file_put_contents($invalidPath, '{invalid-json');
        @unlink($missingPath);

        try {
            $collector = $this->makeCollector(
                [
                    'invalid' => [
                        'class' => Vite::class,
                        '__construct()' => [
                            'configuration' => new ProductionConfiguration($invalidPath, '/invalid'),
                        ],
                    ],
                    'missing' => [
                        'class' => Vite::class,
                        '__construct()' => [
                            'configuration' => new ProductionConfiguration($missingPath, '/missing'),
                        ],
                    ],
                ],
                ['invalid', 'missing'],
            );

            $snapshot = $this->captureSnapshot($collector);

            $invalid = self::componentAt($snapshot, 0);
            $missing = self::componentAt($snapshot, 1);

            self::assertSame(
                [],
                $invalid->chunks(),
                'Malformed JSON must yield no manifest chunks.',
            );
            self::assertSame(
                [],
                $missing->chunks(),
                'A missing manifest must yield no chunks.',
            );
        } finally {
            @unlink($invalidPath);
        }
    }

    public function testCaptureReturnsNullOutsideTheStartedLifecycle(): void
    {
        $configuration = new DevelopmentConfiguration('http://localhost:5173');

        $this->mockWebApplication(['components' => ['vite' => new Vite($configuration)]]);

        Yii::$app->get('vite');

        $collector = new ViteCollector();

        self::assertNull(
            $collector->capture(),
            'An idle collector must record nothing.',
        );

        $collector->startup();
        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'A stopped collector must record nothing.',
        );
    }

    public function testCaptureSupportsPositionalModernConstructorArguments(): void
    {
        $collector = $this->makeCollector(
            [
                'frontend' => [
                    'class' => Vite::class,
                    '__construct()' => [
                        new DevelopmentConfiguration('http://localhost:5173'),
                        [2 => 'resources/js/app.js', 5 => 'resources/js/admin.js'],
                    ],
                ],
            ],
            ['frontend'],
        );

        $component = self::componentAt($this->captureSnapshot($collector), 0);

        self::assertTrue(
            $component->inspectionAvailable,
            'Valid positional Yii constructor arguments must remain inspectable.',
        );
        self::assertSame(
            ViteComponent::MODE_DEVELOPMENT,
            $component->mode,
            'A positional development configuration must select development mode.',
        );
        self::assertSame(
            ['resources/js/app.js', 'resources/js/admin.js'],
            $component->entrypoints,
            'Positional entrypoints must be captured and reindexed in order.',
        );
    }

    public function testIdPairsWithTheVitePanel(): void
    {
        self::assertSame(
            'vite',
            (new ViteCollector())->id(),
            "Stable ID must be 'vite.'."
        );
    }

    /**
     * @return ViteSnapshot Captured non-empty Vite snapshot.
     */
    private function captureSnapshot(ViteCollector $collector): ViteSnapshot
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'A loaded Vite service must produce a snapshot.',
        );

        return $snapshot;
    }

    private static function componentAt(ViteSnapshot $snapshot, int $index): ViteComponent
    {
        return $snapshot->components()[$index] ?? self::fail("The Vite component at index {$index} must exist.");
    }

    /**
     * @param array<string, mixed> $components Application component definitions.
     * @param list<string> $load Component IDs to instantiate before capture.
     */
    private function makeCollector(array $components, array $load): ViteCollector
    {
        $this->mockWebApplication(['components' => $components]);

        foreach ($load as $id) {
            Yii::$app->get($id);
        }

        $collector = new ViteCollector();

        $collector->startup();

        return $collector;
    }
}
