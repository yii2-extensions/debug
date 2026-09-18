<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\Module;
use yii\debug\panels\{LogPanel, ProviderPanel};
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\cache\{Cache, CacheCollector, CachePanel};
use yii\debug\tests\support\stub\ConfigurableAction;

/**
 * Unit tests for {@see Module} covering the `panels` and `collectors` registration contract for portable providers.
 */
#[Group('module')]
final class ExtensionConfigurationTest extends ModuleTestCase
{
    public function testDisabledCollectorEntryIsNotRegistered(): void
    {
        $module = new Module(
            'debug',
            null,
            ['collectors' => ['cache' => ['class' => CacheCollector::class, 'enabled' => false]]],
        );

        self::assertFalse(
            $module->getCollectorCoordinator()->hasCollector('cache'),
            'Disabled entry must stay out of the coordinator.',
        );
    }

    public function testDisabledPanelEntryIsNotRegisteredAndDoesNotInstantiateItsClass(): void
    {
        $module = new Module(
            'debug',
            null,
            [
                'panels' => [
                    'ghost' => ['class' => 'Acme\\Missing\\GhostPanel', 'enabled' => false],
                    'cache' => CachePanel::class,
                ],
            ],
        );

        self::assertArrayNotHasKey(
            'ghost',
            $module->panels,
            'Disabled entry must stay unregistered.',
        );
        self::assertArrayHasKey(
            'cache',
            $module->panels,
            'A disabled entry must not stop later entries.',
        );
    }

    /**
     * Characterization: a replay reads the stored payload only, never the live service.
     */
    public function testHistoricalReplayIgnoresLiveCacheState(): void
    {
        $collector = new CacheCollector();
        $cache = new Cache($collector);

        $module = new Module(
            'debug',
            null,
            ['collectors' => ['cache' => $collector], 'panels' => ['cache' => new CachePanel()]],
        );

        $collector->startup();

        $cache->set('key-a', 1);
        $cache->get('key-a');

        $payload = $collector->capture();

        $collector->shutdown();

        $cache->set('key-b', 2);

        $panel = $module->panels['cache'] ?? null;

        self::assertIsArray(
            $payload,
            'A started collector must yield a payload.',
        );
        self::assertInstanceOf(
            ProviderPanel::class,
            $panel,
            'Portable instance must register through the adapter.',
        );

        $panel->hydrate($payload);

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'key-a',
            $detail,
            'The captured key must survive the replay.',
        );
        self::assertStringNotContainsString(
            'key-b',
            $detail,
            'Activity after the capture must stay out of the replay.',
        );
    }

    public function testPanelsAcceptsPortableClassDefinitionWithMetadataOverrides(): void
    {
        $module = new Module(
            'debug',
            null,
            [
                'panels' => [
                    'cache' => ['class' => CachePanel::class, 'title' => 'Cache operations', 'icon' => 'asset'],
                ],
            ],
        );

        $panel = $module->panels['cache'] ?? null;

        self::assertInstanceOf(
            ProviderPanel::class,
            $panel,
            'Portable class definition must register through the adapter.',
        );
        self::assertSame(
            'Cache operations',
            $panel->getName(),
            'Configured title must win over the provider default.',
        );
        self::assertSame(
            'asset',
            $panel->getToolbarIcon(),
            'Configured icon must win over the provider default.',
        );
    }

    public function testPanelsAcceptsPortableClassStringWithProviderDefaults(): void
    {
        $module = new Module('debug', null, ['panels' => ['cache' => CachePanel::class]]);

        $panel = $module->panels['cache'] ?? null;

        self::assertInstanceOf(
            ProviderPanel::class,
            $panel,
            'Portable class string must register through the adapter.',
        );
        self::assertSame(
            'Cache',
            $panel->getName(),
            'Provider title must apply unchanged.',
        );
        self::assertSame(
            'db',
            $panel->getToolbarIcon(),
            'Provider icon must apply unchanged.',
        );
    }

    /**
     * Characterization: an instance is already accepted today and keeps the provider metadata.
     */
    public function testPanelsAcceptsPortableInstanceWithProviderDefaults(): void
    {
        $module = new Module('debug', null, ['panels' => ['cache' => new CachePanel()]]);

        $panel = $module->panels['cache'] ?? null;

        self::assertInstanceOf(
            ProviderPanel::class,
            $panel,
            'Portable instance must register through the adapter.',
        );
        self::assertSame(
            'Cache',
            $panel->getName(),
            'Provider title must apply unchanged.',
        );
        self::assertSame(
            'db',
            $panel->getToolbarIcon(),
            'Provider icon must apply unchanged.',
        );
    }

    public function testThrowInvalidConfigExceptionForDefinitionThatIsNotAPanel(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Module('debug', null, ['panels' => ['not-panel' => ['class' => ConfigurableAction::class]]]);
    }

    public function testThrowInvalidConfigExceptionForMismatchedPortableClassKey(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'registration ID must match',
        );

        new Module('debug', null, ['panels' => ['wrong' => ['class' => CachePanel::class]]]);
    }

    public function testThrowInvalidConfigExceptionForPositionOnBuiltInPanel(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Module('debug', null, ['panels' => ['log' => ['class' => LogPanel::class, 'position' => 1]]]);
    }

    public function testThrowInvalidConfigExceptionForUnknownPanelOption(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'colour',
        );

        new Module('debug', null, ['panels' => ['cache' => ['class' => CachePanel::class, 'colour' => 'red']]]);
    }
}
