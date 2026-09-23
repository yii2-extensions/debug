<?php

declare(strict_types=1);

namespace yii\debug\tests\vite;

use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPForge\Vite\Vite;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yii;
use yii\base\{Application, InvalidConfigException};
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;

/**
 * End-to-end tests for {@see Module} wiring the real Vite provider through the explicit `collectors`, `panels`, and
 * `dispatchers` recipe the application templates use.
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

    public function testRecipeListsTheViteProviderUnderExtensions(): void
    {
        Yii::$app->set('vite', Vite::class);

        $module = $this->bootstrapModule();

        self::assertTrue(
            $module->getPanelRegistry()->get('vite')?->extension,
            'A provider panel must be grouped under Extensions.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenTheViteComponentIsInstantiatedBeforeTheRequest(): void
    {
        Yii::$app->set('vite', ['class' => Vite::class, '__construct()' => [$this->configuration()]]);
        Yii::$app->get('vite');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(Message::DISPATCHER_COMPONENT_INSTANTIATED->getMessage('vite'));

        $this->bootstrapModule();
    }

    /**
     * Bootstraps a module configured with the Vite recipe and runs the request start hooks.
     *
     * @param array<string, mixed> $config Module configuration merged over the recipe.
     */
    private function bootstrapModule(array $config = []): Module
    {
        $module = new Module(
            'debug',
            null,
            [
                'collectors' => ['vite' => ViteCollector::class],
                'panels' => ['vite' => VitePanel::class],
                'dispatchers' => ['vite' => 'vite'],
                ...$config,
            ],
        );

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
