<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use Yii;
use yii\base\{Application, InvalidConfigException};
use yii\debug\{LogTarget, Module};
use yii\debug\panels\JsonPanel;
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\stub\{CollectorPanel, CustomCollector, StubSnapshot};
use yii\debug\tests\support\TestCase;

use function glob;
use function is_array;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see Module} and {@see LogTarget} custom collector integration.
 *
 * {@see VisibilityProvider} for method contract data providers.
 */
#[Group('collector')]
final class CollectorIntegrationTest extends TestCase
{
    public function testBootstrapStartsCollectorsBeforeRequest(): void
    {
        $collector = new CustomCollector();
        $module = $this->module([$collector]);

        $module->bootstrap(Yii::$app);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertSame(
            1,
            $collector->startupCount,
            'Before-request event must start configured collectors.',
        );

        $module->getCollectorCoordinator()->shutdown();

        $this->cleanup($module);
    }

    public function testCollectorConfigurationSupportsClassNameAndConfigurationArray(): void
    {
        $classModule = $this->module([CustomCollector::class]);

        $configuredModule = $this->module(
            [
                [
                    'class' => CustomCollector::class,
                    'collectorId' => 'app.configured',
                    'value' => 84,
                ],
            ],
        );

        self::assertTrue(
            $classModule->getCollectorCoordinator()->hasCollector('app.example'),
            'Class-name configuration must resolve the collector.',
        );
        self::assertTrue(
            $configuredModule->getCollectorCoordinator()->hasCollector('app.configured'),
            'Configuration array must apply collector properties.',
        );

        $this->cleanup($classModule);
        $this->cleanup($configuredModule);
    }

    public function testCollectorPrecedenceDoesNotSkipLaterLegacyPanels(): void
    {
        $collectorPanel = new CollectorPanel();
        $legacyPanel = new CollectorPanel();

        $legacyPanel->collectorOnly = false;

        $module = $this->module(
            [new CustomCollector()],
            ['app.example' => $collectorPanel, 'legacy' => $legacyPanel],
        );

        $target = new LogTarget($module);

        $target->export();

        $snapshot = $this->store($module)->readSnapshot($target->tag);

        self::assertNotNull(
            $snapshot,
            'Combined collector snapshot must be loadable.',
        );
        self::assertArrayHasKey(
            'app.example',
            $snapshot->panels,
            'Matching collector payload must be persisted.',
        );
        self::assertArrayHasKey(
            'legacy',
            $snapshot->panels,
            'Later legacy panel payload must still be captured.',
        );
        self::assertSame(
            0,
            $collectorPanel->captureCount,
            'Matching panel capture must remain bypassed.',
        );
        self::assertSame(
            1,
            $legacyPanel->captureCount,
            'Later legacy panel must capture once.',
        );

        $this->cleanup($module);
    }

    public function testCustomCollectorPersistsAndMatchingPanelPresentsPayload(): void
    {
        $collector = new CustomCollector();
        $panel = new CollectorPanel();

        $module = $this->module([$collector], ['app.example' => $panel]);

        $target = new LogTarget($module);

        $module->getCollectorCoordinator()->startup();
        $target->export();

        $snapshot = $this->store($module)->readSnapshot($target->tag);

        $summary = $target->loadTagToPanels($target->tag);

        self::assertNotNull(
            $snapshot,
            'Persisted snapshot must be loadable.',
        );
        self::assertArrayHasKey(
            'app.example',
            $snapshot->panels,
            'Custom payload must use the collector ID.',
        );
        self::assertNotNull(
            $summary,
            'Persisted summary must be loadable.',
        );
        self::assertSame(
            '42',
            $panel->getDetail(),
            'Matching panel must present the collector payload.',
        );
        self::assertSame(
            0,
            $panel->captureCount,
            'Matching panel capture must be bypassed.',
        );
        self::assertSame(
            1,
            $collector->startupCount,
            'Collector must start once.',
        );
        self::assertSame(
            1,
            $collector->shutdownCount,
            'Collector must shut down once.',
        );

        $this->cleanup($module);
    }

    /**
     * @param class-string $class
     * @param 'protected'|'public' $expected
     */
    #[DataProviderExternal(VisibilityProvider::class, 'logTargetContracts')]
    public function testExtensionMethodKeepsDeclaredVisibility(string $class, string $method, string $expected): void
    {
        self::assertMethodVisibility($class, $method, $expected);
    }

    public function testFailingCollectorDoesNotEraseLegacyPanelSnapshot(): void
    {
        $collector = new CustomCollector();

        $collector->collectorId = 'broken';
        $collector->failCapture = true;

        $legacy = new CollectorPanel();

        $legacy->collectorOnly = false;

        $module = $this->module([$collector], ['legacy' => $legacy]);

        $target = new LogTarget($module);

        $target->export();

        $snapshot = $this->store($module)->readSnapshot($target->tag);

        self::assertNotNull(
            $snapshot,
            'Persisted snapshot must be loadable.',
        );
        self::assertArrayHasKey(
            'legacy',
            $snapshot->panels,
            'Legacy payload must survive collector failure.',
        );
        self::assertArrayHasKey(
            'broken',
            $snapshot->failures,
            'Collector failure must be persisted.',
        );

        $target->loadTagToPanels($target->tag);

        self::assertInstanceOf(
            JsonPanel::class,
            $module->panels['broken'] ?? null,
            'Failure without payload must receive the JSON fallback panel.',
        );

        $this->cleanup($module);
    }

    public function testLegacyCustomPanelCapturesWithoutCollector(): void
    {
        $panel = new CollectorPanel();

        $panel->collectorOnly = false;

        $module = $this->module([], ['app.example' => $panel]);

        $target = new LogTarget($module);

        $target->export();
        $target->loadTagToPanels($target->tag);

        self::assertSame(
            1,
            $panel->captureCount,
            'Legacy panel capture must remain active.',
        );
        self::assertSame(
            'legacy',
            $panel->getDetail(),
            'Legacy payload must still hydrate and render.',
        );

        $this->cleanup($module);
    }

    public function testThrowInvalidConfigExceptionForDuplicateCollectorId(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Duplicate debug collector ID: app.example.',
        );

        try {
            $this->module([new CustomCollector(), new CustomCollector()]);

            self::fail(
                'Duplicate collector IDs must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                'Duplicate debug collector ID: app.example.',
                $exception->getMessage(),
                'Message must identify the duplicate ID.',
            );
            self::assertSame(
                0,
                $exception->getCode(),
                'Adapter exception code must remain zero.',
            );
            self::assertInstanceOf(
                \InvalidArgumentException::class,
                $exception->getPrevious(),
                'Duplicate collector ID must throw an argument exception.',
            );

            throw $exception;
        }
    }

    public function testThrowInvalidConfigExceptionForEmptyCollectorId(): void
    {
        $collector = new CustomCollector();

        $collector->collectorId = ' ';

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Debug collector ID must not be empty.',
        );

        $this->module([$collector]);
    }

    public function testThrowInvalidConfigExceptionWhenStorageFailsDuringExport(): void
    {
        $collector = new CustomCollector();

        $module = $this->module([$collector]);

        $module->historySize = -1;

        $target = new LogTarget($module);

        $module->getCollectorCoordinator()->startup();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Invalid debug history size: -1',
        );

        try {
            $target->export();
        } finally {
            self::assertSame(
                1,
                $collector->shutdownCount,
                'Collector must shut down when snapshot persistence fails.',
            );

            $this->cleanup($module);
        }
    }

    public function testUnknownStoredPanelUsesEscapedJsonFallback(): void
    {
        $module = $this->module();

        $target = new LogTarget($module);

        $this->writeDebugSnapshot(
            $module,
            'unknown-panel',
            ['app.unknown' => StubSnapshot::capture(['value' => '</code><script>alert(1)</script>'])],
        );

        $target->loadTagToPanels('unknown-panel');

        $panel = $module->panels['app.unknown'] ?? null;

        self::assertInstanceOf(
            JsonPanel::class,
            $panel,
            'Unknown payload must receive the JSON fallback panel.',
        );
        self::assertStringContainsString(
            '&lt;/code&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
            $panel->getDetail(),
            'Fallback JSON must escape stored markup.',
        );
        self::assertStringNotContainsString(
            '</code><script>alert(1)</script>',
            $panel->getDetail(),
            'Raw stored markup must not reach the fallback detail.',
        );

        $this->cleanup($module);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();

        Yii::$app->getRequest()->setUrl('dummy');
    }

    /**
     * Removes isolated storage files created by a test module.
     *
     * @param Module $module Module owning the isolated storage path.
     */
    private function cleanup(Module $module): void
    {
        if (!is_dir($module->dataPath)) {
            return;
        }

        $files = glob("{$module->dataPath}/*");

        foreach (is_array($files) ? $files : [] as $file) {
            @unlink($file);
        }

        @rmdir($module->dataPath);
    }

    /**
     * Creates a module with custom collectors, panels, and isolated storage.
     *
     * @param array<array-key, mixed> $collectors Collector configurations.
     * @param array<string, mixed> $panels Panel configurations.
     *
     * @return Module Configured module.
     */
    private function module(array $collectors = [], array $panels = []): Module
    {
        $module = new Module('debug', null, ['collectors' => $collectors, 'panels' => $panels]);
        $module->dataPath = sys_get_temp_dir() . '/debug-collectors-' . uniqid();

        return $module;
    }

    /**
     * Creates the core store for a module's isolated path.
     *
     * @param Module $module Module owning the storage configuration.
     *
     * @return SnapshotStore Configured snapshot store.
     */
    private function store(Module $module): SnapshotStore
    {
        return new SnapshotStore($module->dataPath, $module->dirMode, $module->fileMode);
    }
}
