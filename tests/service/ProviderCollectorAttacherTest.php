<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yii;
use yii\base\Component;
use yii\debug\{PackagedProvider, ProviderAttachment, ProviderCatalog};
use yii\debug\service\ProviderCollectorAttacher;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\CustomCollector;
use yii\debug\tests\support\stub\inertia\CompatibleManager;
use yii\debug\tests\support\stub\vite\{CompatibleVite, ViteWithoutConstructor, ViteWithoutDispatcher};

/**
 * Unit tests for {@see ProviderCollectorAttacher} handing a provider collector to the component it observes.
 */
#[Group('service')]
final class ProviderCollectorAttacherTest extends ModuleTestCase
{
    /**
     * Application component ID the test provider observes.
     */
    private const string COMPONENT = 'example';
    /**
     * Position the {@see CompatibleVite} constructor declares its `eventDispatcher` parameter at.
     */
    private const int DISPATCHER_POSITION = 1;
    /**
     * Application component ID the provider declared after the test provider observes.
     */
    private const string NEXT_COMPONENT = 'next';
    /**
     * Stable ID of the provider declared after the test provider.
     */
    private const string NEXT_PROVIDER_ID = 'app.next';
    /**
     * Stable ID shared by the test provider and {@see CustomCollector}.
     */
    private const string PROVIDER_ID = 'app.example';

    public function testAttachAmendsAClassNameDefinitionForAConstructorComponent(): void
    {
        $collector = $this->collector();

        Yii::$app->set(
            self::COMPONENT,
            CompatibleVite::class,
        );

        $this
            ->attacher(ProviderAttachment::Constructor, CompatibleVite::class, [$collector])
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleVite::class, '__construct()' => ['eventDispatcher' => $collector]],
            $this->definition(),
            'Class name must be expanded into a definition carrying the collector.',
        );
    }

    public function testAttachAmendsAClassNameDefinitionForAPropertyComponent(): void
    {
        $collector = $this->collector();

        Yii::$app->set(
            self::COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$collector])
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $collector],
            $this->definition(),
            'Class name must be expanded into a definition carrying the collector.',
        );
    }

    public function testAttachAmendsADefinitionWithTheArgumentKeyASubclassDeclares(): void
    {
        $collector = $this->collector();

        Yii::$app->set(
            self::COMPONENT,
            CompatibleVite::class,
        );

        $catalog = new ProviderCatalog($this->provider(ProviderAttachment::Constructor, CompatibleVite::class));
        $coordinator = new CollectorCoordinator([$collector]);

        $attacher = new class ($catalog, $coordinator) extends ProviderCollectorAttacher {
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

        $this
            ->attacher(ProviderAttachment::Constructor, CompatibleVite::class, [$collector])
            ->attach(Yii::$app);

        self::assertFalse(
            Yii::$app->has(self::COMPONENT, true),
            'Attachment must not instantiate the component.',
        );

        $arguments = $this->constructorArguments();

        self::assertSame(
            $configuration,
            $arguments['configuration'] ?? null,
            'Named arguments must survive.',
        );
        self::assertSame(
            $collector,
            $arguments['eventDispatcher'] ?? null,
            'Definition must carry the collector.',
        );
    }

    public function testAttachAmendsAPositionalConstructorDefinitionAtTheDeclaredPosition(): void
    {
        $collector = $this->collector();
        $configuration = $this->configuration();

        Yii::$app->set(
            self::COMPONENT,
            [
                'class' => CompatibleVite::class,
                '__construct()' => [$configuration],
            ],
        );

        $this
            ->attacher(ProviderAttachment::Constructor, CompatibleVite::class, [$collector])
            ->attach(Yii::$app);

        $arguments = $this->constructorArguments();

        self::assertSame(
            $configuration,
            $arguments[0] ?? null,
            'Positional arguments must survive.',
        );
        self::assertSame(
            $collector,
            $arguments[self::DISPATCHER_POSITION] ?? null,
            'Mixing named and positional arguments is rejected by Yii.',
        );
    }

    public function testAttachAmendsARegisteredInstanceForAPropertyComponent(): void
    {
        $collector = $this->collector();

        $manager = new CompatibleManager();

        Yii::$app->set(
            self::COMPONENT,
            $manager,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$collector])
            ->attach(Yii::$app);

        self::assertSame(
            $collector,
            $manager->eventDispatcher,
            'Registered instance must receive the collector.',
        );
    }

    public function testAttachIgnoresAMissingComponent(): void
    {
        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertFalse(
            Yii::$app->has(self::COMPONENT),
            'No component may be registered on behalf of the application.',
        );
    }

    public function testAttachKeepsADispatcherTheDefinitionConfigures(): void
    {
        $own = $this->dispatcher();

        Yii::$app->set(
            self::COMPONENT,
            [
                'class' => CompatibleManager::class,
                'eventDispatcher' => $own,
            ],
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $own],
            $this->definition(),
            'Configured dispatcher must win.',
        );
    }

    public function testAttachKeepsADispatcherTheInstanceCarries(): void
    {
        $own = $this->dispatcher();

        $manager = new CompatibleManager();

        $manager->eventDispatcher = $own;

        Yii::$app->set(
            self::COMPONENT,
            $manager,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $own,
            $manager->eventDispatcher,
            'Configured dispatcher must win.',
        );
    }

    public function testAttachKeepsADispatcherThePositionalDefinitionConfigures(): void
    {
        $own = $this->dispatcher();

        Yii::$app->set(
            self::COMPONENT,
            [
                'class' => CompatibleVite::class,
                '__construct()' => [$this->configuration(), $own],
            ],
        );

        $this
            ->attacher(ProviderAttachment::Constructor, CompatibleVite::class, [$this->collector()])
            ->attach(Yii::$app);

        $arguments = $this->constructorArguments();

        self::assertSame(
            $own,
            $arguments[self::DISPATCHER_POSITION] ?? null,
            'Configured dispatcher must win.',
        );
        self::assertArrayNotHasKey(
            'eventDispatcher',
            $arguments,
            'No second dispatcher may be added.',
        );
    }

    public function testAttachKeepsGoingAfterACollectorOutsideTheDispatcherContract(): void
    {
        $next = $this->collector(self::NEXT_PROVIDER_ID);

        Yii::$app->set(
            self::COMPONENT,
            CompatibleManager::class,
        );
        Yii::$app->set(
            self::NEXT_COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->chainedAttacher(
                $this->provider(ProviderAttachment::Property, CompatibleManager::class),
                [new CustomCollector(), $next],
            )
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $next],
            $this->definition(self::NEXT_COMPONENT),
            'The next component must receive its collector.',
        );
    }

    public function testAttachKeepsGoingAfterADefinitionWithoutADispatcherParameter(): void
    {
        $next = $this->collector(self::NEXT_PROVIDER_ID);

        Yii::$app->set(
            self::COMPONENT,
            [
                'class' => ViteWithoutDispatcher::class,
                '__construct()' => [$this->configuration()],
            ],
        );
        Yii::$app->set(
            self::NEXT_COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->chainedAttacher(
                $this->provider(ProviderAttachment::Constructor, ViteWithoutDispatcher::class),
                [$this->collector(), $next],
            )
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $next],
            $this->definition(self::NEXT_COMPONENT),
            'The next component must receive its collector.',
        );
    }

    public function testAttachKeepsGoingAfterAForeignDefinition(): void
    {
        $next = $this->collector(self::NEXT_PROVIDER_ID);

        Yii::$app->set(
            self::COMPONENT,
            ['class' => Component::class],
        );
        Yii::$app->set(
            self::NEXT_COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->chainedAttacher(
                $this->provider(ProviderAttachment::Property, CompatibleManager::class),
                [$this->collector(), $next],
            )
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $next],
            $this->definition(self::NEXT_COMPONENT),
            'The next component must receive its collector.',
        );
    }

    public function testAttachKeepsGoingAfterALiveInstance(): void
    {
        $next = $this->collector(self::NEXT_PROVIDER_ID);

        Yii::$app->set(
            self::COMPONENT,
            new CompatibleManager(),
        );
        Yii::$app->set(
            self::NEXT_COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->chainedAttacher(
                $this->provider(ProviderAttachment::Property, CompatibleManager::class),
                [$this->collector(), $next],
            )
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => CompatibleManager::class, 'eventDispatcher' => $next],
            $this->definition(self::NEXT_COMPONENT),
            'The next component must receive its collector.',
        );
    }

    public function testAttachLeavesAClosureDefinitionAlone(): void
    {
        $factory = static fn(): CompatibleManager => new CompatibleManager();

        Yii::$app->set(
            self::COMPONENT,
            $factory,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $factory,
            $this->definition(),
            'Closure definition must stay untouched.',
        );
    }

    public function testAttachLeavesAComponentWithoutADispatcherPropertyAlone(): void
    {
        $component = new Component();

        Yii::$app->set(
            self::COMPONENT,
            $component,
        );

        $this
            ->attacher(ProviderAttachment::Property, Component::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $component,
            Yii::$app->get(self::COMPONENT),
            'A component with no dispatcher property must survive the attachment.',
        );
    }

    public function testAttachLeavesADefinitionWithoutAResolvableClassAlone(): void
    {
        $definition = ['class' => 'Acme\\Missing\\ExampleComponent'];

        Yii::$app->set(
            self::COMPONENT,
            $definition,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $definition,
            $this->definition(),
            'An unresolvable definition must stay untouched.',
        );
    }

    public function testAttachLeavesAForeignDefinitionAlone(): void
    {
        Yii::$app->set(
            self::COMPONENT,
            ['class' => Component::class],
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            ['class' => Component::class],
            $this->definition(),
            'Foreign definition must stay untouched.',
        );
    }

    public function testAttachLeavesALiveComponentOfAConstructorProviderAlone(): void
    {
        $manager = new CompatibleManager();

        Yii::$app->set(
            self::COMPONENT,
            $manager,
        );

        $this
            ->attacher(ProviderAttachment::Constructor, CompatibleManager::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertNull(
            $manager->eventDispatcher,
            'A constructor provider must not write the property.',
        );
    }

    public function testAttachLeavesAnInstanceOutsideTheDeclaredComponentClassAlone(): void
    {
        $manager = new CompatibleManager();

        Yii::$app->set(
            self::COMPONENT,
            $manager,
        );

        $this
            ->attacher(ProviderAttachment::Property, ViteWithoutDispatcher::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertNull(
            $manager->eventDispatcher,
            'An instance outside the declared class must stay untouched.',
        );
    }

    public function testAttachLeavesAnInstantiatedConstructorComponentAlone(): void
    {
        Yii::$app->set(
            self::COMPONENT,
            ['class' => CompatibleVite::class, '__construct()' => [$this->configuration()]],
        );

        $vite = Yii::$app->get(self::COMPONENT);

        $this
            ->attacher(ProviderAttachment::Constructor, CompatibleVite::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $vite,
            Yii::$app->get(self::COMPONENT),
            'Live component must survive the attachment.',
        );
        self::assertArrayNotHasKey(
            self::DISPATCHER_POSITION,
            $this->constructorArguments(),
            'A constructor argument cannot reach an instantiated component.',
        );
    }

    public function testAttachLeavesAPositionalDefinitionWithoutAConstructorAlone(): void
    {
        $definition = ['class' => ViteWithoutConstructor::class, '__construct()' => ['unused']];

        Yii::$app->set(
            self::COMPONENT,
            $definition,
        );

        $this
            ->attacher(ProviderAttachment::Constructor, ViteWithoutConstructor::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $definition,
            $this->definition(),
            'A class declaring no constructor must stay untouched.',
        );
    }

    public function testAttachLeavesAPositionalDefinitionWithoutADispatcherParameterAlone(): void
    {
        $definition = [
            'class' => ViteWithoutDispatcher::class,
            '__construct()' => [$this->configuration()],
        ];

        Yii::$app->set(
            self::COMPONENT,
            $definition,
        );

        $this
            ->attacher(ProviderAttachment::Constructor, ViteWithoutDispatcher::class, [$this->collector()])
            ->attach(Yii::$app);

        self::assertSame(
            $definition,
            $this->definition(),
            'Definition must stay untouched.',
        );
    }

    public function testAttachSkipsACollectorOutsideTheDispatcherContract(): void
    {
        Yii::$app->set(
            self::COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [new CustomCollector()])
            ->attach(Yii::$app);

        self::assertSame(
            CompatibleManager::class,
            $this->definition(),
            'A collector outside the dispatcher contract must not be attached.',
        );
    }

    public function testAttachSkipsAnUninstalledProvider(): void
    {
        Yii::$app->set(
            self::COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->attacher(
                ProviderAttachment::Property,
                CompatibleManager::class,
                [$this->collector()],
                'Acme\\Missing\\ExampleCollector',
            )
            ->attach(Yii::$app);

        self::assertSame(
            CompatibleManager::class,
            $this->definition(),
            'An uninstalled provider must not be attached.',
        );
    }

    public function testAttachSkipsAProviderWhoseCollectorIsNotRegistered(): void
    {
        Yii::$app->set(
            self::COMPONENT,
            CompatibleManager::class,
        );

        $this
            ->attacher(ProviderAttachment::Property, CompatibleManager::class, [])
            ->attach(Yii::$app);

        self::assertSame(
            CompatibleManager::class,
            $this->definition(),
            'An unregistered collector must not be attached.',
        );
    }

    /**
     * Builds the attacher for one provider observing {@see COMPONENT}.
     *
     * @param ProviderAttachment $attachment Way the component takes the collector.
     * @param class-string $componentClass Class the component must be for the attachment to apply.
     * @param list<CollectorInterface> $collectors Collectors the coordinator holds.
     * @param string $collectorClass Collector class deciding whether the provider package counts as installed.
     */
    private function attacher(
        ProviderAttachment $attachment,
        string $componentClass,
        array $collectors,
        string $collectorClass = CustomCollector::class,
    ): ProviderCollectorAttacher {
        return new ProviderCollectorAttacher(
            new ProviderCatalog(
                $this->provider($attachment, $componentClass, collectorClass: $collectorClass),
            ),
            new CollectorCoordinator($collectors),
        );
    }

    /**
     * Builds the attacher for `$first` followed by a provider observing {@see NEXT_COMPONENT}.
     *
     * @param PackagedProvider $first Provider the scenario makes the attachment skip.
     * @param list<CollectorInterface> $collectors Collectors the coordinator holds.
     */
    private function chainedAttacher(PackagedProvider $first, array $collectors): ProviderCollectorAttacher
    {
        return new ProviderCollectorAttacher(
            new ProviderCatalog(
                $first,
                $this->provider(
                    ProviderAttachment::Property,
                    CompatibleManager::class,
                    self::NEXT_PROVIDER_ID,
                    self::NEXT_COMPONENT,
                ),
            ),
            new CollectorCoordinator($collectors),
        );
    }

    /**
     * Builds a collector the provider component may take as its PSR-14 dispatcher.
     *
     * @param string $id Stable ID the coordinator indexes the collector under.
     */
    private function collector(string $id = self::PROVIDER_ID): CollectorInterface&EventDispatcherInterface
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
     * Builds one provider observing an application component.
     *
     * @param ProviderAttachment $attachment Way the component takes the collector.
     * @param class-string $componentClass Class the component must be for the attachment to apply.
     * @param string $id Stable ID the provider shares with its collector.
     * @param string $component Application component ID the provider observes.
     * @param string $collectorClass Collector class deciding whether the provider package counts as installed.
     */
    private function provider(
        ProviderAttachment $attachment,
        string $componentClass,
        string $id = self::PROVIDER_ID,
        string $component = self::COMPONENT,
        string $collectorClass = CustomCollector::class,
    ): PackagedProvider {
        return new PackagedProvider(
            $id,
            $collectorClass,
            'Acme\\Missing\\ExamplePanel',
            $component,
            $componentClass,
            $attachment,
        );
    }
}
