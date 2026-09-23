<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPForge\Debug\CollectorInterface;
use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use Yii;
use yii\base\{Component, InvalidConfigException};
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\service\DispatcherAttacher;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\CustomCollector;
use yii\debug\tests\support\stub\dispatcher\{
    PrivateBackedAccessorComponent,
    ProtectedDispatcherComponent,
    ReadonlyAccessorComponent,
    SetterComponent,
    SetterOnlyComponent,
    StaticDispatcherComponent,
    UninitializedDispatcherComponent,
};
use yii\debug\tests\support\stub\inertia\CompatibleManager;
use yii\debug\tests\support\stub\vite\{CompatibleVite, ViteWithoutConstructor, ViteWithoutDispatcher};

/**
 * Unit tests for {@see DispatcherAttacher} handing each collector named in `dispatchers` to its application component.
 */
#[Group('service')]
final class DispatcherAttacherTest extends ModuleTestCase
{
    /**
     * Stable ID of the test collector.
     */
    private const string COLLECTOR_ID = 'app.example';
    /**
     * Application component ID the test collector is handed to.
     */
    private const string COMPONENT = 'example';
    /**
     * Position the {@see CompatibleVite} constructor declares its `eventDispatcher` parameter at.
     */
    private const int DISPATCHER_POSITION = 1;

    public function testAttachAmendsAClassNameDefinitionForAConstructorComponent(): void
    {
        $collector = $this->collector();

        Yii::$app->set(self::COMPONENT, CompatibleVite::class);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleVite::class, '__construct()' => ['eventDispatcher' => $collector]],
            $this->definition(),
            'A readonly promoted property must fall back to the constructor.',
        );
    }

    public function testAttachAmendsAClassNameDefinitionForAPropertyComponent(): void
    {
        $collector = $this->collector();

        Yii::$app->set(self::COMPONENT, CompatibleManager::class);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $collector],
            $this->definition(),
            'Class name must be expanded into a definition carrying the collector.',
        );
    }

    public function testAttachAmendsADefinitionWithTheArgumentKeyASubclassDeclares(): void
    {
        $collector = $this->collector();

        Yii::$app->set(self::COMPONENT, CompatibleVite::class);

        $attacher = new class ($this->module([$collector])) extends DispatcherAttacher {
            /**
             * @param class-string $class Component class whose constructor is inspected.
             * @param array<array-key, mixed> $arguments Arguments the definition already declares.
             */
            protected function dispatcherArgumentKey(string $class, array $arguments): string
            {
                return 'dispatcher';
            }
        };

        $attacher->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleVite::class, '__construct()' => ['dispatcher' => $collector]],
            $this->definition(),
            'Argument key must stay a late-bound extension point.',
        );
    }

    public function testAttachAmendsANamedConstructorDefinitionWithoutInstantiatingIt(): void
    {
        $collector = $this->collector();
        $configuration = $this->configuration();

        Yii::$app->set(
            self::COMPONENT,
            ['class' => CompatibleVite::class, '__construct()' => ['configuration' => $configuration]],
        );

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertFalse(
            Yii::$app->has(self::COMPONENT, true),
            'Attachment must not instantiate the component.',
        );
        self::assertSame(
            ['configuration' => $configuration, 'eventDispatcher' => $collector],
            $this->constructorArguments(),
            'Named arguments must gain the collector by name.',
        );
    }

    public function testAttachAmendsAnInstanceWhosePublicDispatcherIsUninitialized(): void
    {
        $collector = $this->collector();

        $component = (new ReflectionClass(UninitializedDispatcherComponent::class))->newInstanceWithoutConstructor();

        Yii::$app->set(self::COMPONENT, $component);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            $collector,
            $component->eventDispatcher,
            'An uninitialized property must count as `null`.',
        );
    }

    public function testAttachAmendsAnInstantiatedPrivateBackedAccessorComponent(): void
    {
        $collector = $this->collector();

        $component = new PrivateBackedAccessorComponent();

        Yii::$app->set(self::COMPONENT, $component);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            $collector,
            $component->getEventDispatcher(),
            'A private backing property must defer to the Yii setter.',
        );
    }

    public function testAttachAmendsAnInstantiatedYiiSetterComponent(): void
    {
        $collector = $this->collector();

        $component = new SetterComponent();

        Yii::$app->set(self::COMPONENT, $component);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            $collector,
            $component->getEventDispatcher(),
            'Instance must receive the collector through its setter.',
        );
    }

    public function testAttachAmendsAPositionalConstructorDefinitionAtTheDeclaredPosition(): void
    {
        $collector = $this->collector();
        $configuration = $this->configuration();

        Yii::$app->set(self::COMPONENT, ['class' => CompatibleVite::class, '__construct()' => [$configuration]]);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            [$configuration, self::DISPATCHER_POSITION => $collector],
            $this->constructorArguments(),
            'Positional arguments must gain the collector at its declared position.',
        );
        self::assertInstanceOf(
            CompatibleVite::class,
            Yii::$app->get(self::COMPONENT),
            'Component must stay buildable.',
        );
    }

    public function testAttachAmendsAPrivateBackedAccessorDefinition(): void
    {
        $collector = $this->collector();

        Yii::$app->set(self::COMPONENT, PrivateBackedAccessorComponent::class);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            ['class' => PrivateBackedAccessorComponent::class, 'eventDispatcher' => $collector],
            $this->definition(),
            'A private backing property must defer to the Yii accessors.',
        );
    }

    public function testAttachAmendsARegisteredInstanceForAPropertyComponent(): void
    {
        $collector = $this->collector();

        $component = new CompatibleManager();

        Yii::$app->set(self::COMPONENT, $component);

        $this->attacher([$collector])->attach(Yii::$app);

        self::assertSame(
            $collector,
            $component->eventDispatcher,
            'Instance must receive the collector.',
        );
    }

    public function testAttachAmendsAYiiSetterDefinition(): void
    {
        $collector = $this->collector();

        Yii::$app->set(self::COMPONENT, SetterComponent::class);

        $this->attacher([$collector])->attach(Yii::$app);

        $component = Yii::$app->get(self::COMPONENT);

        self::assertInstanceOf(
            SetterComponent::class,
            $component,
            'Component must build from the amended definition.',
        );
        self::assertSame(
            $collector,
            $component->getEventDispatcher(),
            'A getter/setter pair counts as the property form.',
        );
    }

    public function testAttachHandsEveryConfiguredCollectorToItsOwnComponent(): void
    {
        $first = $this->collector();
        $second = $this->collector('app.next');

        Yii::$app->set(self::COMPONENT, CompatibleManager::class);
        Yii::$app->set('next', CompatibleManager::class);

        $this
            ->attacher([$first, $second], [self::COLLECTOR_ID => self::COMPONENT, 'app.next' => 'next'])
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $first],
            $this->definition(),
            'First entry must reach its component.',
        );
        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $second],
            $this->definition('next'),
            'Second entry must reach its own component.',
        );
    }

    public function testAttachKeepsADispatcherTheDefinitionConfigures(): void
    {
        $own = $this->dispatcher();

        Yii::$app->set(self::COMPONENT, ['class' => CompatibleManager::class, 'eventDispatcher' => $own]);

        $this->attacher([$this->collector()])->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $own],
            $this->definition(),
            'Configured dispatcher must win.',
        );
    }

    public function testAttachKeepsADispatcherTheInstanceCarries(): void
    {
        $own = $this->dispatcher();

        $component = new CompatibleManager(['eventDispatcher' => $own]);

        Yii::$app->set(self::COMPONENT, $component);

        $this->attacher([$this->collector()])->attach(Yii::$app);

        self::assertSame(
            $own,
            $component->eventDispatcher,
            'Configured dispatcher must win.',
        );
    }

    public function testAttachKeepsADispatcherThePositionalDefinitionConfigures(): void
    {
        $own = $this->dispatcher();
        $configuration = $this->configuration();

        Yii::$app->set(
            self::COMPONENT,
            ['class' => CompatibleVite::class, '__construct()' => [$configuration, $own]],
        );

        $this->attacher([$this->collector()])->attach(Yii::$app);

        self::assertSame(
            [$configuration, $own],
            $this->constructorArguments(),
            'Configured dispatcher must win.',
        );
    }

    public function testAttachKeepsGoingAfterADisabledCollector(): void
    {
        $collector = $this->collector();

        Yii::$app->set(self::COMPONENT, CompatibleManager::class);

        $module = new Module(
            'debug',
            null,
            [
                'collectors' => [
                    'app.disabled' => ['class' => CustomCollector::class, 'enabled' => false],
                    self::COLLECTOR_ID => $collector,
                ],
                'dispatchers' => ['app.disabled' => 'other', self::COLLECTOR_ID => self::COMPONENT],
            ],
        );

        (new DispatcherAttacher($module))->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $collector],
            $this->definition(),
            'An entry after a disabled one must still attach.',
        );
    }

    public function testAttachSkipsACollectorDisabledByConfiguration(): void
    {
        Yii::$app->set(self::COMPONENT, CompatibleManager::class);

        $module = new Module(
            'debug',
            null,
            [
                'collectors' => [self::COLLECTOR_ID => ['class' => CustomCollector::class, 'enabled' => false]],
                'dispatchers' => [self::COLLECTOR_ID => self::COMPONENT],
            ],
        );

        (new DispatcherAttacher($module))->attach(Yii::$app);

        self::assertSame(
            CompatibleManager::class,
            $this->definition(),
            'Definition must stay untouched.',
        );
    }

    /**
     * @param class-string $class Component class declaring no usable dispatcher target.
     */
    #[TestWith([Component::class])]
    #[TestWith([ProtectedDispatcherComponent::class])]
    #[TestWith([ReadonlyAccessorComponent::class])]
    #[TestWith([SetterOnlyComponent::class])]
    #[TestWith([StaticDispatcherComponent::class])]
    #[TestWith([ViteWithoutConstructor::class])]
    #[TestWith([ViteWithoutDispatcher::class])]
    public function testThrowInvalidConfigExceptionForAClassTakingNoDispatcher(string $class): void
    {
        Yii::$app->set(self::COMPONENT, ['class' => $class]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage(self::COMPONENT),
        );

        $this->attacher([$this->collector()])->attach(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenAnInstantiatedComponentHasNoWritableProperty(): void
    {
        Yii::$app->set(self::COMPONENT, new CompatibleVite($this->configuration()));

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DISPATCHER_COMPONENT_INSTANTIATED->getMessage(self::COMPONENT),
        );

        $this->attacher([$this->collector()])->attach(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenTheCollectorIsNotADispatcher(): void
    {
        Yii::$app->set(self::COMPONENT, CompatibleManager::class);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DISPATCHER_COLLECTOR_NOT_DISPATCHER->getMessage(
                self::COLLECTOR_ID,
                EventDispatcherInterface::class,
            ),
        );

        $this->attacher([new CustomCollector()])->attach(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenTheComponentIsAClosureDefinition(): void
    {
        Yii::$app->set(self::COMPONENT, static fn(): CompatibleManager => new CompatibleManager());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage(self::COMPONENT),
        );

        $this->attacher([$this->collector()])->attach(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenTheComponentIsUnknown(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DISPATCHER_COMPONENT_UNKNOWN->getMessage(self::COLLECTOR_ID, self::COMPONENT),
        );

        $this->attacher([$this->collector()])->attach(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenTheDefinitionNamesAContainerAliasOnly(): void
    {
        Yii::$container->set('acme.alias', CompatibleManager::class);
        Yii::$app->set(self::COMPONENT, ['class' => 'acme.alias']);

        try {
            $this->expectException(InvalidConfigException::class);
            $this->expectExceptionMessage(
                Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage(self::COMPONENT),
            );

            $this->attacher([$this->collector()])->attach(Yii::$app);
        } finally {
            Yii::$container->clear('acme.alias');
        }
    }

    public function testThrowInvalidConfigExceptionWhenTheDefinitionNamesNoLoadableClass(): void
    {
        Yii::$app->set(self::COMPONENT, ['class' => 'Acme\\Missing\\Component']);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DISPATCHER_TARGET_UNSUPPORTED->getMessage(self::COMPONENT),
        );

        $this->attacher([$this->collector()])->attach(Yii::$app);
    }

    /**
     * Builds an attacher over a module registering `$collectors` and handing them out as `$dispatchers` declares.
     *
     * @param list<CollectorInterface> $collectors Collectors the module registers.
     * @param array<string, string> $dispatchers Collector ID to component ID map.
     */
    private function attacher(
        array $collectors,
        array $dispatchers = [self::COLLECTOR_ID => self::COMPONENT],
    ): DispatcherAttacher {
        return new DispatcherAttacher($this->module($collectors, $dispatchers));
    }

    /**
     * Builds a collector the component may take as its PSR-14 dispatcher.
     *
     * @param string $id Stable ID the coordinator indexes the collector under.
     */
    private function collector(string $id = self::COLLECTOR_ID): CollectorInterface&EventDispatcherInterface
    {
        return new class ($id) implements CollectorInterface, EventDispatcherInterface {
            public function __construct(private readonly string $id) {}

            public function capture(): array|null
            {
                return null;
            }

            public function dispatch(object $event): object
            {
                return $event;
            }

            public function id(): string
            {
                return $this->id;
            }

            public function shutdown(): void {}

            public function startup(): void {}
        };
    }

    private function configuration(): DevelopmentConfiguration
    {
        return new DevelopmentConfiguration('http://localhost:5173');
    }

    /**
     * Returns the constructor arguments of the registered component definition.
     *
     * @return array<array-key, mixed> Arguments the definition passes to the component.
     */
    private function constructorArguments(): array
    {
        $definition = $this->definition();

        self::assertIsArray(
            $definition,
            'Definition must remain registered.',
        );

        $arguments = $definition['__construct()'] ?? null;

        self::assertIsArray(
            $arguments,
            'Definition must keep its constructor arguments.',
        );

        return $arguments;
    }

    private function definition(string $id = self::COMPONENT): mixed
    {
        return Yii::$app->getComponents()[$id] ?? null;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
    }

    /**
     * Builds a module registering `$collectors` and handing them out as `$dispatchers` declares.
     *
     * @param list<CollectorInterface> $collectors Collectors the module registers.
     * @param array<string, string> $dispatchers Collector ID to component ID map.
     */
    private function module(array $collectors, array $dispatchers = [self::COLLECTOR_ID => self::COMPONENT]): Module
    {
        return new Module('debug', null, ['collectors' => $collectors, 'dispatchers' => $dispatchers]);
    }
}
