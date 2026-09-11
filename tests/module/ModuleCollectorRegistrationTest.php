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

/**
 * Unit tests for {@see Module} covering collector override ordering, invalid collector configuration and contracts,
 * optional extension detection, and explicit collector/panel configuration when a provider is unavailable.
 */
#[Group('module')]
final class ModuleCollectorRegistrationTest extends ModuleTestCase
{
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
