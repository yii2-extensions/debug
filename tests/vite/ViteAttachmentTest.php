<?php

declare(strict_types=1);

namespace yii\debug\tests\vite;

use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPForge\Vite\Debug\ViteCollector;
use PHPForge\Vite\Vite;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yii;
use yii\base\{Application, Component};
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;

/**
 * Unit tests for {@see Module} handing the Vite collector to the `vite` application component.
 */
#[Group('module')]
#[Group('vite')]
final class ViteAttachmentTest extends ModuleTestCase
{
    /**
     * Position the Vite constructor declares its `eventDispatcher` parameter at.
     */
    private const int DISPATCHER_POSITION = 3;

    public function testBootstrapAmendsAClassNameDefinition(): void
    {
        Yii::$app->set('vite', Vite::class);

        $module = $this->bootstrapModule();

        self::assertSame(
            ['class' => Vite::class, '__construct()' => ['eventDispatcher' => $this->collector($module)]],
            $this->definition(),
            'Class name must be expanded into a definition carrying the collector.',
        );
    }

    public function testBootstrapAmendsANamedDefinitionWithoutInstantiatingIt(): void
    {
        $configuration = $this->configuration();

        Yii::$app->set('vite', ['class' => Vite::class, '__construct()' => ['configuration' => $configuration]]);

        $module = $this->bootstrapModule();

        self::assertFalse(
            Yii::$app->has('vite', true),
            'Attachment must not instantiate the component.',
        );

        $arguments = $this->constructorArguments();

        self::assertSame(
            $configuration,
            $arguments['configuration'] ?? null,
            'Named arguments must survive.',
        );
        self::assertSame(
            $this->collector($module),
            $arguments['eventDispatcher'] ?? null,
            'Definition must carry the collector.',
        );
    }

    public function testBootstrapAmendsAPositionalDefinitionAtTheDeclaredPosition(): void
    {
        $configuration = $this->configuration();

        Yii::$app->set('vite', ['class' => Vite::class, '__construct()' => [$configuration]]);

        $module = $this->bootstrapModule();

        $arguments = $this->constructorArguments();

        self::assertSame(
            $configuration,
            $arguments[0] ?? null,
            'Positional arguments must survive.',
        );
        self::assertSame(
            $this->collector($module),
            $arguments[self::DISPATCHER_POSITION] ?? null,
            'Mixing named and positional arguments is rejected by Yii.',
        );
    }

    public function testBootstrapIgnoresAMissingComponent(): void
    {
        $this->bootstrapModule();

        self::assertFalse(
            Yii::$app->has('vite'),
            'No component may be registered on behalf of the application.',
        );
    }

    public function testBootstrapKeepsADispatcherTheDefinitionConfigures(): void
    {
        $own = $this->dispatcher();

        Yii::$app->set(
            'vite',
            ['class' => Vite::class, '__construct()' => [$this->configuration(), [], null, $own]],
        );

        $this->bootstrapModule();

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

    public function testBootstrapKeepsAPositionalDefinitionBuildable(): void
    {
        Yii::$app->set('vite', ['class' => Vite::class, '__construct()' => [$this->configuration()]]);

        $this->bootstrapModule();

        self::assertInstanceOf(
            Vite::class,
            Yii::$app->get('vite'),
            'Yii rejects a definition mixing named and positional arguments.',
        );
    }

    public function testBootstrapLeavesAClosureDefinitionAlone(): void
    {
        $factory = fn(): Vite => new Vite($this->configuration());

        Yii::$app->set('vite', $factory);

        $this->bootstrapModule();

        self::assertSame(
            $factory,
            $this->definition(),
            'Closure definition must stay untouched.',
        );
    }

    public function testBootstrapLeavesAComponentInstantiatedBeforeTheRequestAlone(): void
    {
        Yii::$app->set('vite', ['class' => Vite::class, '__construct()' => [$this->configuration()]]);

        $vite = Yii::$app->get('vite');

        $this->bootstrapModule();

        self::assertSame(
            $vite,
            Yii::$app->get('vite'),
            'Live component must survive the attachment.',
        );
        self::assertArrayNotHasKey(
            self::DISPATCHER_POSITION,
            $this->constructorArguments(),
            'A constructor argument cannot reach an instantiated component.',
        );
    }

    public function testBootstrapLeavesAForeignComponentAlone(): void
    {
        Yii::$app->set('vite', ['class' => Component::class]);

        $this->bootstrapModule();

        self::assertSame(
            ['class' => Component::class],
            $this->definition(),
            'Foreign definition must stay untouched.',
        );
    }

    public function testBootstrapSkipsAttachmentWhenTheCollectorIsDisabled(): void
    {
        Yii::$app->set('vite', ['class' => Vite::class, '__construct()' => [$this->configuration()]]);

        $this->bootstrapModule(
            ['collectors' => ['vite' => ['class' => ViteCollector::class, 'enabled' => false]]],
        );

        self::assertArrayNotHasKey(
            self::DISPATCHER_POSITION,
            $this->constructorArguments(),
            'No collector may be attached.',
        );
    }

    /**
     * Bootstraps a module on the current application and runs the request start hooks.
     *
     * @param array<string, mixed> $config Module configuration.
     */
    private function bootstrapModule(array $config = []): Module
    {
        $module = new Module('debug', null, $config);

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        return $module;
    }

    private function collector(Module $module): ViteCollector
    {
        $collector = $module->getCollectorCoordinator()->collector('vite');

        self::assertInstanceOf(
            ViteCollector::class,
            $collector,
            'Module must register the Vite collector.',
        );

        return $collector;
    }

    private function configuration(): DevelopmentConfiguration
    {
        return new DevelopmentConfiguration('http://localhost:5173');
    }

    /**
     * Returns the constructor arguments of the registered `vite` definition.
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

    private function definition(): mixed
    {
        return Yii::$app->getComponents()['vite'] ?? null;
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
}
