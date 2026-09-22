<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use Closure;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{Application, InvalidConfigException, Module as BaseModule};
use yii\debug\exception\Message;
use yii\debug\{Module, ProviderCatalog};
use yii\debug\service\{AccessGuard, CollectorRegistrar, ProviderCollectorAttacher};
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\service\ConfiguredAccessGuard;

/**
 * Unit tests for {@see Module} resolving its own services through the module service locator, binding this module to
 * the definitions an application registers.
 */
#[Group('module')]
final class ModuleServiceTest extends ModuleTestCase
{
    public function testCheckAccessUsesTheGuardConfiguredThroughModuleComponents(): void
    {
        $module = new Module(
            'debug',
            null,
            [
                'components' => [
                    AccessGuard::class => ['class' => ConfiguredAccessGuard::class],
                ],
            ],
        );

        $module->allowedIPs = ['*'];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            'Configured guard must decide the outcome.',
        );

        $guard = $module->get(AccessGuard::class);

        self::assertInstanceOf(
            ConfiguredAccessGuard::class,
            $guard,
            'Component definition must replace the default guard.',
        );
        self::assertSame(
            ['127.0.0.1'],
            $guard->calls,
            'Guard must receive the requesting IP address.',
        );
        self::assertSame(
            $module,
            $guard->boundModule(),
            'Module must reach the constructor.',
        );
    }

    public function testServiceAppliesTheConfiguredPropertiesOfAnArrayDefinition(): void
    {
        $module = new Module('debug');

        $module->set(
            AccessGuard::class,
            [
                'class' => ConfiguredAccessGuard::class,
                'label' => 'configured',
            ],
        );

        $guard = $this->resolveGuard($module);

        self::assertInstanceOf(
            ConfiguredAccessGuard::class,
            $guard,
            'Array definition must produce the configured class.',
        );
        self::assertSame(
            'configured',
            $guard->label,
            'Configured property must reach the instance.',
        );
        self::assertSame(
            $module,
            $guard->boundModule(),
            'Module must reach the constructor.',
        );
    }

    public function testServiceBuildsAClassNameDefinitionWithTheModule(): void
    {
        $module = new Module('debug');

        $module->set(AccessGuard::class, ConfiguredAccessGuard::class);

        $guard = $this->resolveGuard($module);

        self::assertInstanceOf(
            ConfiguredAccessGuard::class,
            $guard,
            'Class-name definition must produce the configured class.',
        );
        self::assertSame(
            $module,
            $guard->boundModule(),
            'Module must reach the constructor.',
        );
    }

    public function testServiceBuildsAnArrayDefinitionWithTheModule(): void
    {
        $module = new Module('debug');

        $module->set(AccessGuard::class, ['class' => ConfiguredAccessGuard::class]);

        $guard = $this->resolveGuard($module);

        self::assertInstanceOf(
            ConfiguredAccessGuard::class,
            $guard,
            'Array definition must produce the configured class.',
        );
        self::assertSame(
            $module,
            $guard->boundModule(),
            'Module must reach the constructor.',
        );
    }

    public function testServiceCachesTheInstanceBuiltByTheDefaultFactory(): void
    {
        $module = new Module('debug');

        $factory = static fn(): stdClass => new stdClass();

        self::assertSame(
            $this->resolve($module, $factory),
            $this->resolve($module, $factory),
            'Locator must return the same instance on every resolution.',
        );
    }

    public function testServiceHandsTheModuleToAClosureDeclaringIt(): void
    {
        $module = new Module('debug');

        $module->set(
            AccessGuard::class,
            static fn(Module $module): AccessGuard => new ConfiguredAccessGuard($module),
        );

        $guard = $this->resolveGuard($module);

        self::assertInstanceOf(
            ConfiguredAccessGuard::class,
            $guard,
            'Closure definition must produce the configured class.',
        );
        self::assertSame(
            $module,
            $guard->boundModule(),
            'Declared `module` parameter must receive this module.',
        );
    }

    public function testServiceIgnoresAParentModuleDefinition(): void
    {
        $parent = new BaseModule('parent');

        $parent->set(AccessGuard::class, new stdClass());

        $module = new Module('debug', $parent);

        self::assertInstanceOf(
            AccessGuard::class,
            $this->resolveGuard($module),
            'Foreign parent definition must stay out of the resolution.',
        );
    }

    public function testServiceKeepsAClosureWithoutParametersWorking(): void
    {
        $module = new Module('debug');
        $guard = new ConfiguredAccessGuard($module);

        $module->set(AccessGuard::class, static fn(): AccessGuard => $guard);

        self::assertSame(
            $guard,
            $this->resolveGuard($module),
            'Parameterless closure must still be invoked.',
        );
    }

    public function testServiceRegistersTheDefaultFactoryWhenNoDefinitionExists(): void
    {
        $module = new Module('debug');
        $expected = new stdClass();

        self::assertFalse(
            $module->has(stdClass::class),
            'Locator must start without the service definition.',
        );
        self::assertSame(
            $expected,
            $this->resolve($module, static fn(): stdClass => $expected),
            'Default factory must build the service.',
        );
        self::assertTrue(
            $module->has(stdClass::class),
            'Locator must keep the registered definition.',
        );
    }

    public function testServiceReturnsThePreRegisteredLocatorInstance(): void
    {
        $module = new Module('debug');
        $registered = new stdClass();

        $module->set(stdClass::class, $registered);

        $built = false;
        $factory = static function () use (&$built): stdClass {
            $built = true;

            return new stdClass();
        };

        self::assertSame(
            $registered,
            $this->resolve($module, $factory),
            'Pre-registered definition must win.',
        );
        self::assertFalse(
            $built,
            'Default factory must stay unused.',
        );
    }

    public function testServiceUsesTheAttacherRegisteredOnTheLocatorDuringBootstrap(): void
    {
        $module = new Module('debug');

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $catalog = ProviderCatalog::packaged();
        $coordinator = $module->getCollectorCoordinator();

        $attacher = new class ($catalog, $coordinator) extends ProviderCollectorAttacher {
            public int $attachCalls = 0;

            public function attach(Application $app): void
            {
                $this->attachCalls++;
            }
        };

        $module->set(ProviderCollectorAttacher::class, static fn(): ProviderCollectorAttacher => $attacher);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertSame(
            1,
            $attacher->attachCalls,
            'Replaced service must receive the attachment call.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenServiceDefinitionResolvesToForeignType(): void
    {
        $module = new Module('debug');

        $module->set(CollectorRegistrar::class, new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::SERVICE_INSTANCE_INVALID->getMessage(CollectorRegistrar::class),
        );

        $this->invoke(
            $module,
            'service',
            [CollectorRegistrar::class, static fn(): CollectorRegistrar => new CollectorRegistrar($module)],
        );
    }

    /**
     * Resolves a `stdClass` service through the module locator.
     *
     * @param Closure(): stdClass $factory Factory building the default instance.
     */
    private function resolve(Module $module, Closure $factory): mixed
    {
        return $this->invoke($module, 'service', [stdClass::class, $factory]);
    }

    /**
     * Resolves the {@see AccessGuard} service through the module locator, defaulting to the built-in guard.
     */
    private function resolveGuard(Module $module): mixed
    {
        return $this->invoke(
            $module,
            'service',
            [AccessGuard::class, static fn(): AccessGuard => new AccessGuard($module)],
        );
    }
}
