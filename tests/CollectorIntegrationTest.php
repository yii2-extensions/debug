<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{Application, InvalidConfigException};
use yii\debug\{LogTarget, Module};
use yii\debug\panels\JsonPanel;
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

    public function testCollectorPrecedenceDoesNotSkipLaterSelfCapturingPanels(): void
    {
        $collectorPanel = new CollectorPanel();
        $selfCapturingPanel = new CollectorPanel();

        $selfCapturingPanel->collectorOnly = false;

        $module = $this->module(
            [new CustomCollector()],
            ['app.example' => $collectorPanel, 'custom' => $selfCapturingPanel],
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
            'custom',
            $snapshot->panels,
            'Later self-capturing panel payload must still be captured.',
        );
        self::assertSame(
            0,
            $collectorPanel->captureCount,
            'Matching panel capture must remain bypassed.',
        );
        self::assertSame(
            1,
            $selfCapturingPanel->captureCount,
            'Later self-capturing panel must capture once.',
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

        $snapshot = $this
            ->store($module)
            ->readSnapshot($target->tag);

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

    public function testCustomPanelCapturesWithoutCollector(): void
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
            'Custom panel capture must remain active.',
        );
        self::assertSame(
            'custom',
            $panel->getDetail(),
            'Custom panel payload must still hydrate and render.',
        );

        $this->cleanup($module);
    }

    public function testFailingCollectorDoesNotEraseSelfCapturingPanelSnapshot(): void
    {
        $collector = new CustomCollector();

        $collector->collectorId = 'broken';
        $collector->failCapture = true;

        $selfCapturingPanel = new CollectorPanel();

        $selfCapturingPanel->collectorOnly = false;

        $module = $this->module([$collector], ['custom' => $selfCapturingPanel]);

        $target = new LogTarget($module);

        $target->export();

        $snapshot = $this
            ->store($module)
            ->readSnapshot($target->tag);

        self::assertNotNull(
            $snapshot,
            'Persisted snapshot must be loadable.',
        );
        self::assertArrayHasKey(
            'custom',
            $snapshot->panels,
            'Custom panel payload must survive collector failure.',
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

    public function testUnavailableExtensionStoredPayloadDoesNotCreateFallbackPanel(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $module = $this->module();

        $target = new LogTarget($module);

        $this->writeDebugSnapshot(
            $module,
            'unavailable-queue',
            ['queue' => StubSnapshot::capture(['value' => 'custom queue payload'])],
        );

        $target->loadTagToPanels('unavailable-queue');

        self::assertArrayNotHasKey(
            'queue',
            $module->panels,
            'A stored payload must not resurrect an extension whose provider is unavailable.',
        );

        $this->cleanup($module);
    }

    public function testUnavailableExtensionStoredPayloadKeepsExplicitCollectorFallback(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $collector = new CustomCollector();

        $collector->collectorId = 'queue';

        $module = $this->module([$collector]);

        $target = new LogTarget($module);

        $this->writeDebugSnapshot(
            $module,
            'custom-queue',
            ['queue' => StubSnapshot::capture(['value' => 'custom queue payload'])],
        );

        $target->loadTagToPanels('custom-queue');

        self::assertInstanceOf(
            JsonPanel::class,
            $module->panels['queue'] ?? null,
            'An explicitly configured collector must retain the generic fallback for its stored payload.',
        );

        $this->cleanup($module);
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
