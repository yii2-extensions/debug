<?php

declare(strict_types=1);

namespace yii\debug\tests\inertia;

use PHPForge\Inertia\Debug\InertiaCollector;
use PHPUnit\Framework\Attributes\Group;
use Psr\EventDispatcher\EventDispatcherInterface;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{Application, Component};
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\inertia\CompatibleManager;
use yii\inertia\Manager as InertiaManager;

/**
 * Unit tests for {@see Module} handing the Inertia collector to the `inertia` application component.
 */
#[Group('module')]
#[Group('inertia')]
final class InertiaAttachmentTest extends ModuleTestCase
{
    public function testBootstrapAmendsAClassNameDefinition(): void
    {
        Yii::$app->set('inertia', InertiaManager::class);

        $module = $this->bootstrapModule();

        self::assertSame(
            $this->collector($module),
            $this->manager()->eventDispatcher,
            'Class name must be expanded into a definition carrying the collector.',
        );
    }

    public function testBootstrapAmendsACompatibleManagerDefinition(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'is_a',
            [CompatibleManager::class, InertiaManager::class, true],
            true,
        );

        Yii::$app->set('inertia', ['class' => CompatibleManager::class]);

        $module = $this->bootstrapModule();

        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            CompatibleManager::class,
            $manager,
            'Component must keep its configured class.',
        );
        self::assertSame(
            $this->collector($module),
            $manager->eventDispatcher,
            'Definition must carry the collector.',
        );
    }

    public function testBootstrapAmendsAManagerDefinitionWithoutInstantiatingIt(): void
    {
        Yii::$app->set('inertia', ['class' => InertiaManager::class]);

        $module = $this->bootstrapModule();

        self::assertFalse(
            Yii::$app->has('inertia', true),
            'Attachment must not instantiate the component.',
        );
        self::assertSame(
            $this->collector($module),
            $this->manager()->eventDispatcher,
            'Definition must carry the collector.',
        );
    }

    public function testBootstrapAmendsAnInstantiatedManager(): void
    {
        Yii::$app->set('inertia', ['class' => InertiaManager::class]);

        $manager = $this->manager();
        $module = $this->bootstrapModule();

        self::assertSame(
            $this->collector($module),
            $manager->eventDispatcher,
            'Instance must receive the collector.',
        );
    }

    public function testBootstrapAmendsARegisteredManagerInstance(): void
    {
        $manager = new InertiaManager();

        Yii::$app->set('inertia', $manager);

        $module = $this->bootstrapModule();

        self::assertSame(
            $this->collector($module),
            $manager->eventDispatcher,
            'Registered instance must receive the collector.',
        );
    }

    public function testBootstrapIgnoresAMissingComponent(): void
    {
        $this->bootstrapModule();

        self::assertFalse(
            Yii::$app->has('inertia'),
            'No component may be registered on behalf of the application.',
        );
    }

    public function testBootstrapKeepsADispatcherTheDefinitionConfigures(): void
    {
        $own = $this->dispatcher();

        Yii::$app->set('inertia', ['class' => InertiaManager::class, 'eventDispatcher' => $own]);

        $this->bootstrapModule();

        self::assertSame(
            $own,
            $this->manager()->eventDispatcher,
            'Configured dispatcher must win.',
        );
    }

    public function testBootstrapKeepsADispatcherTheInstanceCarries(): void
    {
        $own = $this->dispatcher();
        $manager = new InertiaManager(['eventDispatcher' => $own]);

        Yii::$app->set('inertia', $manager);

        $this->bootstrapModule();

        self::assertSame(
            $own,
            $manager->eventDispatcher,
            'Configured dispatcher must win.',
        );
    }

    public function testBootstrapLeavesAClosureDefinitionAlone(): void
    {
        Yii::$app->set('inertia', static fn(): InertiaManager => new InertiaManager());

        $this->bootstrapModule();

        self::assertNull(
            $this->manager()->eventDispatcher,
            'Closure definition must stay untouched.',
        );
    }

    public function testBootstrapLeavesAForeignComponentAlone(): void
    {
        Yii::$app->set('inertia', ['class' => Component::class]);

        $this->bootstrapModule();

        self::assertSame(
            ['class' => Component::class],
            Yii::$app->getComponents()['inertia'] ?? null,
            'Foreign definition must stay untouched.',
        );
    }

    public function testBootstrapSkipsAttachmentWhenTheCollectorIsDisabled(): void
    {
        Yii::$app->set('inertia', ['class' => InertiaManager::class]);

        $this->bootstrapModule(
            ['collectors' => ['inertia' => ['class' => InertiaCollector::class, 'enabled' => false]]],
        );

        $definition = Yii::$app->getComponents()['inertia'] ?? null;

        self::assertIsArray(
            $definition,
            'Definition must remain registered.',
        );
        self::assertArrayNotHasKey(
            'eventDispatcher',
            $definition,
            'No collector may be attached.',
        );
    }

    public function testProtocolResultsReachTheCollectorWithHostRedaction(): void
    {
        Yii::$app->set('inertia', ['class' => InertiaManager::class]);

        $module = $this->bootstrapModule();

        $request = Yii::$app->getRequest();

        $request->setHostInfo('https://example.test');
        $request->setUrl('/home');
        $request->getHeaders()->set('X-Inertia', 'true');

        $this->manager()->render('Home', ['answer' => 42, 'password' => 'secret']);

        $capture = $this->collector($module)->capture();

        self::assertIsArray(
            $capture,
            'A rendered page must be captured.',
        );

        $page = $capture['page'] ?? null;

        self::assertIsArray(
            $page,
            'Capture must carry the page.',
        );
        self::assertSame(
            'Home',
            $page['component'] ?? null,
            'Capture must name the rendered component.',
        );

        $props = $page['props'] ?? null;

        self::assertIsArray(
            $props,
            'Capture must carry the page props.',
        );
        self::assertSame(
            42,
            $props['answer'] ?? null,
            'Plain props must survive.',
        );
        self::assertNotSame(
            'secret',
            $props['password'] ?? null,
            'Sensitive props must follow the module policy.',
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

    private function collector(Module $module): InertiaCollector
    {
        $collector = $module->getCollectorCoordinator()->collector('inertia');

        self::assertInstanceOf(InertiaCollector::class, $collector, 'Module must register the Inertia collector.');

        return $collector;
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

    private function manager(): InertiaManager
    {
        $manager = Yii::$app->get('inertia');

        self::assertInstanceOf(
            InertiaManager::class,
            $manager,
            'Component must be the Yii2 adapter.',
        );

        return $manager;
    }
}
