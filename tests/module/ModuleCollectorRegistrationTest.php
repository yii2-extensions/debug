<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPForge\Debug\CollectorInterface;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use yii\base\InvalidConfigException;
use yii\debug\collectors\{LogCollector, QueueCollector};
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\panels\QueuePanel;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\CustomCollector;

/**
 * Unit tests for {@see Module} covering collector override ordering, invalid collector configuration and contracts,
 * optional extension detection, and explicit collector/panel configuration when a provider is unavailable.
 */
#[Group('module')]
final class ModuleCollectorRegistrationTest extends ModuleTestCase
{
    public function testInitCollectorsKeepsRegisteringEntriesDeclaredAfterADisabledOne(): void
    {
        $module = new Module(
            'debug',
            null,
            [
                'collectors' => [
                    'log' => ['class' => LogCollector::class, 'enabled' => false],
                    'app.example' => new CustomCollector(),
                ],
            ],
        );

        $coordinator = $module->getCollectorCoordinator();

        self::assertFalse(
            $coordinator->hasCollector('log'),
            'Disabled entry must stay out of the coordinator.',
        );
        self::assertTrue(
            $coordinator->hasCollector('app.example'),
            'A later entry must still be registered.',
        );
    }

    public function testInitCollectorsMoveOverriddenCoreCollectorToConfiguredPosition(): void
    {
        $this->mockWebApplication();

        $module = new Module(
            'debug',
            null,
            ['collectors' => ['log' => LogCollector::class]],
        );

        $ids = [];

        foreach ($module->collectors as $collector) {
            self::assertInstanceOf(
                CollectorInterface::class,
                $collector,
                'Collectors must resolve to instances.',
            );

            $ids[] = $collector->id();
        }

        self::assertNotSame(
            [],
            $ids,
            'Collectors must be registered.'
        );
        self::assertSame(
            'log',
            $ids[array_key_last($ids)],
            'Override must move the collector to its configured slot.'
        );
    }

    public function testInitCollectorsRejectsConfigurationWithoutResolvableClass(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_CLASS_INVALID->getMessage(),
        );

        new Module(
            'debug',
            null,
            ['collectors' => [['class' => 'No\Such\Collector']]],
        );
    }

    public function testInitCollectorsRejectsNonBooleanEnabledOption(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_ENABLED_INVALID->getMessage('log'),
        );

        new Module(
            'debug',
            null,
            ['collectors' => ['log' => ['class' => LogCollector::class, 'enabled' => 'yes']]],
        );
    }

    public function testInitCollectorsRejectsObjectWithoutCollectorInterface(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_INTERFACE_INVALID->getMessage(CollectorInterface::class, stdClass::class),
        );

        new Module(
            'debug',
            null,
            ['collectors' => [stdClass::class]],
        );
    }

    public function testInitOmitsTheInertiaProviderWhenItsPackageIsMissing(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['PHPForge\Inertia\Debug\InertiaCollector'],
            false,
        );

        $module = new Module('debug');

        self::assertFalse(
            $module->getCollectorCoordinator()->hasCollector('inertia'),
            'A missing Inertia package must not start a collector.',
        );
        self::assertArrayNotHasKey(
            'inertia',
            $module->panels,
            'A missing Inertia package must not register a panel.',
        );
    }

    public function testInitOmitsUnavailableCoreExtensionCollectorAndPanel(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $module = new Module('debug');

        self::assertFalse(
            $module->getCollectorCoordinator()->hasCollector('queue'),
            'An unavailable core extension must not start a collector.',
        );
        self::assertArrayNotHasKey(
            'queue',
            $module->panels,
            'An unavailable core extension must not register a panel.',
        );
    }

    public function testInitPreservesExplicitExtensionConfigurationWhenProviderIsUnavailable(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $module = new Module(
            'debug',
            null,
            [
                'collectors' => ['queue' => QueueCollector::class],
                'panels' => ['queue' => QueuePanel::class],
            ],
        );

        self::assertTrue(
            $module->getCollectorCoordinator()->hasCollector('queue'),
            'An explicitly configured collector must override automatic extension detection.',
        );
        self::assertInstanceOf(
            QueuePanel::class,
            $module->panels['queue'] ?? null,
            'An explicitly configured panel must override automatic extension detection.',
        );
        self::assertArrayHasKey(
            'queue-job',
            $module->actionMap,
            'An explicitly configured extension panel must still contribute its standalone actions.',
        );
    }
}
