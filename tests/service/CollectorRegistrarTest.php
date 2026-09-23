<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use Closure;
use InvalidArgumentException;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Storage\PanelSnapshot;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use yii\base\InvalidConfigException;
use yii\debug\collectors\{Collector, LogCollector, QueueCollector};
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\service\CollectorRegistrar;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\CustomCollector;

/**
 * Unit tests for {@see CollectorRegistrar} resolving collector definitions into a coordinator.
 */
#[Group('service')]
final class CollectorRegistrarTest extends ModuleTestCase
{
    public function testRegisterAcceptsADispatcherEntryNamingADisabledCollector(): void
    {
        $module = new Module('debug');

        $module->dispatchers = ['app.example' => 'example'];

        $coordinator = (new CollectorRegistrar($module))->register(
            [],
            ['app.example' => ['class' => CustomCollector::class, 'enabled' => false]],
        );

        self::assertFalse(
            $coordinator->hasCollector('app.example'),
            'A disabled collector must stay unregistered.',
        );
    }

    public function testRegisterAppendsConfiguredCollectorsAfterTheBuiltIns(): void
    {
        $coordinator = $this->registrar()->register(
            ['log' => LogCollector::class],
            ['app.example' => new CustomCollector()],
        );

        self::assertSame(
            ['log', 'app.example'],
            array_keys($coordinator->collectors()),
            'Configured entries must follow the built-ins.',
        );
    }

    public function testRegisterBindsTheModuleAndInstrumentsAYiiCollector(): void
    {
        $module = new Module('debug');
        $collector = new class extends Collector {
            public int $instrumentCalls = 0;

            public function id(): string
            {
                return 'stub';
            }

            public function instrument(): void
            {
                $this->instrumentCalls++;
            }

            protected function snapshot(): PanelSnapshot|null
            {
                return null;
            }
        };

        (new CollectorRegistrar($module))->register([], ['stub' => $collector]);

        self::assertSame(
            $module,
            $collector->module,
            'Module reference must be bound.',
        );
        self::assertSame(
            1,
            $collector->instrumentCalls,
            'Instrumentation must run once.',
        );
    }

    public function testRegisterBuildsClosureEntriesWithOneModuleCapturePolicy(): void
    {
        $module = new Module('debug', null, ['sensitiveKeys' => ['token']]);

        $policies = [];

        $factory = static function (string $id) use (&$policies): Closure {
            return static function (CapturePolicy $policy) use ($id, &$policies): CustomCollector {
                $policies[] = $policy;

                $collector = new CustomCollector();

                $collector->collectorId = $id;

                return $collector;
            };
        };

        $coordinator = (new CollectorRegistrar($module))->register(
            [],
            ['app.first' => $factory('app.first'), 'app.second' => $factory('app.second')],
        );

        self::assertSame(
            ['app.first', 'app.second'],
            array_keys($coordinator->collectors()),
            'Every closure entry must register its collector.',
        );
        self::assertCount(
            2,
            $policies,
            'Each closure must be called once.',
        );
        self::assertSame(
            $policies[0],
            $policies[1],
            'One policy must serve every closure of a call.',
        );
        self::assertSame(
            ['token' => SensitiveDataRedactor::PLACEHOLDER],
            $policies[0]->redact(['token' => 'secret']),
            'Policy must carry the module sensitive keys.',
        );
    }

    public function testRegisterDropsABuiltInWhoseOptionalPackageIsMissing(): void
    {
        MockerState::addCondition(
            'yii\debug\service',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $coordinator = $this->registrar()->register(['queue' => QueueCollector::class], []);

        self::assertFalse(
            $coordinator->hasCollector('queue'),
            'An unavailable integration must not start a collector.',
        );
    }

    public function testRegisterKeepsACollectorInstanceVerbatim(): void
    {
        $collector = new CustomCollector();

        $coordinator = $this->registrar()->register([], ['app.example' => $collector]);

        self::assertSame(
            $collector,
            $coordinator->collector('app.example'),
            'Instance must be reused without rebuilding it.',
        );
    }

    public function testRegisterKeepsRegisteringEntriesDeclaredAfterADisabledOne(): void
    {
        $coordinator = $this->registrar()->register(
            [],
            [
                'log' => ['class' => LogCollector::class, 'enabled' => false],
                'app.example' => new CustomCollector(),
            ],
        );

        self::assertFalse(
            $coordinator->hasCollector('log'),
            'Disabled entry must stay out of the coordinator.',
        );
        self::assertTrue(
            $coordinator->hasCollector('app.example'),
            'A later entry must still be registered.',
        );
    }

    public function testRegisterSkipsADisabledEntryBeforeReachingTheAutoloader(): void
    {
        $registrar = $this->registrar();

        $requested = [];
        $spy = static function (string $class) use (&$requested): void {
            $requested[] = $class;
        };

        spl_autoload_register($spy, true, true);

        try {
            $coordinator = $registrar->register(
                [],
                ['ghost' => ['class' => 'Acme\\Missing\\GhostCollector', 'enabled' => false]],
            );
        } finally {
            spl_autoload_unregister($spy);
        }

        self::assertNotContains(
            'Acme\\Missing\\GhostCollector',
            $requested,
            'Disabled entry must not reach the autoloader.',
        );
        self::assertSame(
            [],
            $coordinator->collectors(),
            'Disabled entry must stay out of the coordinator.',
        );
    }

    public function testRegisterStripsTheEnabledFlagFromAnAcceptedDefinition(): void
    {
        $coordinator = $this->registrar()->register(
            [],
            ['log' => ['class' => LogCollector::class, 'enabled' => true]],
        );

        self::assertInstanceOf(
            LogCollector::class,
            $coordinator->collector('log'),
            'An enabled entry must resolve through the container.',
        );
    }

    public function testThrowInvalidConfigExceptionForConfigurationWithoutResolvableClass(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_CLASS_INVALID->getMessage(),
        );

        $this->registrar()->register([], [['class' => 'Acme\\Missing\\GhostCollector']]);
    }

    public function testThrowInvalidConfigExceptionForNonBooleanEnabledFlag(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_ENABLED_INVALID->getMessage('log'),
        );

        $this->registrar()->register([], ['log' => ['class' => LogCollector::class, 'enabled' => 'yes']]);
    }

    public function testThrowInvalidConfigExceptionForObjectOutsideTheCollectorContract(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_INTERFACE_INVALID->getMessage(CollectorInterface::class, stdClass::class),
        );

        $this->registrar()->register([], [stdClass::class]);
    }

    public function testThrowInvalidConfigExceptionWhenAClosureEntryReturnsNoCollector(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::COLLECTOR_INTERFACE_INVALID->getMessage(CollectorInterface::class, stdClass::class),
        );

        $this->registrar()->register([], ['app.example' => static fn(CapturePolicy $policy): stdClass => new stdClass()]);
    }

    public function testThrowInvalidConfigExceptionWhenACollectorIdContradictsItsRegistrationKey(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PROVIDER_ID_MISMATCH->getMessage('collector'),
        );

        $this->registrar()->register([], ['wrong' => new CustomCollector()]);
    }

    public function testThrowInvalidConfigExceptionWhenADispatcherEntryNamesNoConfiguredCollector(): void
    {
        $module = new Module('debug');

        $module->dispatchers = ['app.missing' => 'example'];

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(Message::DISPATCHER_COLLECTOR_UNKNOWN->getMessage('app.missing'));

        (new CollectorRegistrar($module))->register([], ['app.example' => new CustomCollector()]);
    }

    public function testThrowInvalidConfigExceptionWhenTwoCollectorsShareAnId(): void
    {
        try {
            $this->registrar()->register([], [new CustomCollector(), new CustomCollector()]);

            self::fail('A duplicate collector ID must be rejected.');
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                0,
                $exception->getCode(),
                'Code must stay at `0`.',
            );
            self::assertInstanceOf(
                InvalidArgumentException::class,
                $exception->getPrevious(),
                'Original cause must be chained.',
            );
        }
    }

    private function registrar(): CollectorRegistrar
    {
        return new CollectorRegistrar(new Module('debug'));
    }
}
