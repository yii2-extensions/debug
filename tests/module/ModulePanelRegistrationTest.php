<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPForge\Debug\Panel as PortablePanel;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\debug\exception\Message;
use yii\debug\{ExtensionAvailability, Module, Panel};
use yii\debug\panels\{DbPanel, LogPanel};
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\{
    ConfigIdRecordingPanel,
    ConfigurableAction,
    CustomDbPanel,
    CustomPanel,
    ModuleBoundRecordingPanel,
};
use yii\debug\tests\support\stub\cache\CachePanel;

/**
 * Unit tests for {@see Module} covering panel class/configuration/instance resolution, invalid and disabled panels,
 * `moduleBound` invocation, override ordering, and service-locator registration by concrete and ancestor classes.
 */
#[Group('module')]
final class ModulePanelRegistrationTest extends ModuleTestCase
{
    public function testGetPanelRegistryCarriesTheIconDeclaredByEachPanel(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['custom' => new CustomPanel()]],
        );

        $registry = $module->getPanelRegistry();

        self::assertSame(
            'logs',
            $registry->get('log')?->icon,
            'Declared icon must reach the catalog.',
        );
        self::assertSame(
            '',
            $registry->get('custom')?->icon,
            'A panel with no icon must register an empty key.',
        );
    }

    public function testGetPanelRegistryReportsPanelsDisabledByConfiguration(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['ghost' => ['class' => 'Acme\\Missing\\GhostPanel', 'enabled' => false]]],
        );

        $registry = $module->getPanelRegistry();

        self::assertContains(
            'ghost',
            $registry->disabled(),
            'Disabled entry must be reported.',
        );
        self::assertTrue(
            $registry->isDisabled('ghost'),
            'Disabled entry must answer `true`.',
        );
        self::assertNotSame(
            [],
            $registry->enabled(),
            'Built-in panels must survive the disabled entry.',
        );
    }

    public function testInitDoesNotRegisterTheGenericPanelBaseClass(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        self::assertFalse(
            $module->has(Panel::class),
            'Generic base must stay unregistered.',
        );
    }

    public function testInitPanelsAcceptsArrayConfigWithExtraProperties(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['log' => ['class' => LogPanel::class]]],
        );

        self::assertArrayHasKey(
            'log',
            $module->panels,
            "'log' panel must surface after array-config resolution.",
        );
        self::assertInstanceOf(
            LogPanel::class,
            $module->panels['log'],
            "Array-shaped panel config with 'class' key must be resolved through the container.",
        );
    }

    public function testInitPanelsAcceptsPanelInstanceVerbatim(): void
    {
        $existing = new LogPanel();
        $module = new Module(
            'debug',
            null,
            ['panels' => ['log-instance' => $existing]],
        );

        self::assertArrayHasKey(
            'log-instance',
            $module->panels,
            'Panel-instance config must surface under its id.'
        );
        self::assertSame(
            $existing,
            $module->panels['log-instance'],
            "Panel instances passed in config must be reused verbatim with 'id' bound.",
        );
        self::assertSame(
            'log-instance',
            $existing->id,
            'Existing panel must receive the configured ID.',
        );
    }

    public function testInitPanelsAcceptsStringPanelClass(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['log-string' => LogPanel::class]],
        );

        self::assertArrayHasKey(
            'log-string',
            $module->panels,
            'Class-name configuration must create a panel.',
        );
        self::assertInstanceOf(
            LogPanel::class,
            $module->panels['log-string'],
            "String-built panel must be a 'LogPanel' instance.",
        );
    }

    public function testInitPanelsBindsIntegerRegistrationKeyAsStringPanelId(): void
    {
        $panel = new class extends Panel {
            public function isEnabled(): bool
            {
                return false;
            }
        };

        $module = new Module(
            'debug',
            null,
            ['panels' => [$panel]],
        );

        self::assertSame(
            '0',
            $panel->id,
            'Integer key must bind as a `string`.',
        );
        self::assertNotContains(
            $panel,
            $module->panels,
            'A disabled panel must stay unregistered.',
        );
    }

    public function testInitPanelsDoesNotAutoloadTheClassOfADisabledEntry(): void
    {
        $requested = [];
        $spy = static function (string $class) use (&$requested): void {
            $requested[] = $class;
        };

        spl_autoload_register($spy, true, true);

        try {
            $module = new Module(
                'debug',
                null,
                ['panels' => ['ghost' => ['class' => 'Acme\\Missing\\GhostPanel', 'enabled' => false]]],
            );
        } finally {
            spl_autoload_unregister($spy);
        }

        self::assertNotContains(
            'Acme\\Missing\\GhostPanel',
            $requested,
            'Disabled entry must not reach the autoloader.',
        );
        self::assertTrue(
            $module->getPanelRegistry()->isDisabled('ghost'),
            'Disabled entry must be reported.',
        );
    }

    public function testInitPanelsDropsDisabledPanels(): void
    {
        $disabled = new class extends Panel {
            public function isEnabled(): bool
            {
                return false;
            }
        };

        $module = new Module(
            'debug',
            null,
            ['panels' => ['ghost' => $disabled]],
        );

        self::assertArrayNotHasKey(
            'ghost',
            $module->panels,
            'Disabled panels must be removed.',
        );
    }

    public function testInitPanelsFiresModuleBoundOnceForEveryResolutionPath(): void
    {
        $instance = new ModuleBoundRecordingPanel();

        $module = new Module(
            'debug',
            null,
            [
                'panels' => [
                    'rec-array' => ['class' => ModuleBoundRecordingPanel::class],
                    'rec-instance' => $instance,
                ],
            ],
        );

        self::assertSame(
            1,
            $instance->moduleBoundCalls,
            'Exactly one invocation on the instance path.',
        );
        self::assertTrue(
            $instance->moduleBoundWithModule,
            'Module reference must be present at call time.',
        );

        self::assertArrayHasKey(
            'rec-array',
            $module->panels,
            'Array-built panel must surface under its id.',
        );

        $arrayPanel = $module->panels['rec-array'];

        self::assertInstanceOf(
            ModuleBoundRecordingPanel::class,
            $arrayPanel,
            'Array-built panel must resolve through the container.',
        );
        self::assertSame(
            1,
            $arrayPanel->moduleBoundCalls,
            'Exactly one invocation on the array path.',
        );
        self::assertTrue(
            $arrayPanel->moduleBoundWithModule,
            'Module reference must be present at call time.',
        );
    }

    public function testInitPanelsMoveOverriddenCorePanelToConfiguredPosition(): void
    {
        $this->mockWebApplication();

        $module = new Module(
            'debug',
            null,
            ['panels' => ['log' => LogPanel::class]],
        );

        $builtIns = array_filter(
            $module->panels,
            static fn(Panel $panel, string $id): bool => ExtensionAvailability::isExtensionPanel($id, $panel) === false,
            ARRAY_FILTER_USE_BOTH,
        );

        self::assertSame(
            'log',
            array_key_last($builtIns),
            'Override must move the panel to its configured slot.',
        );
    }

    public function testInitPanelsOverridesCorePanelByMatchingId(): void
    {
        $override = new LogPanel();
        $module = new Module(
            'debug',
            null,
            ['panels' => ['log' => $override]],
        );

        self::assertArrayHasKey(
            'log',
            $module->panels,
            'Override entry must surface under the same id.',
        );
        self::assertSame(
            $override,
            $module->panels['log'],
            'Matching custom panel must replace the core entry.',
        );
    }

    public function testInitPanelsPassesIntegerRegistrationKeyToTheContainerAsString(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => [ConfigIdRecordingPanel::class]],
        );

        $panel = $module->panels[ConfigIdRecordingPanel::ID] ?? null;

        self::assertInstanceOf(
            ConfigIdRecordingPanel::class,
            $panel,
            'Class-name entry must build through the container.',
        );
        self::assertSame(
            '0',
            $panel->configuredId,
            'Integer key must reach the configuration as a `string`.',
        );
    }

    public function testInitRegistersEnabledPanelsInServiceLocatorUnderTheirClass(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'db' => [
                        'class' => Connection::class,
                        'dsn' => 'sqlite::memory:',
                    ],
                ],
            ],
        );

        $module = new Module('debug');

        self::assertTrue(
            $module->has(DbPanel::class),
            'DB panel must be locatable by its class.',
        );
        self::assertSame(
            $module->panels['db'] ?? null,
            $module->get(DbPanel::class),
            'Locator must return the registered panel instance.',
        );
    }

    public function testInitRegistersPanelAncestorClassesBelowThePanelBase(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'db' => [
                        'class' => Connection::class,
                        'dsn' => 'sqlite::memory:',
                    ],
                ],
            ],
        );

        $module = new Module(
            'debug',
            null,
            ['panels' => ['db' => CustomDbPanel::class]],
        );

        self::assertSame(
            $module->panels['db'] ?? null,
            $module->get(CustomDbPanel::class),
            'Subclass key must resolve to the configured instance.',
        );
        self::assertSame(
            $module->panels['db'],
            $module->get(DbPanel::class),
            'Built-in class key must resolve to the same subclass instance.',
        );
    }

    public function testInitSkipsServiceRegistrationForDisabledPanels(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        self::assertFalse(
            $module->has(DbPanel::class),
            'Disabled panel must not be locatable.',
        );
    }

    public function testThrowInvalidConfigExceptionForConfigurationThatIsNotAPanel(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'must resolve to a',
        );

        new Module(
            'debug',
            null,
            ['panels' => ['not-panel' => ['class' => ConfigurableAction::class]]],
        );
    }

    public function testThrowInvalidConfigExceptionForMetadataOverrideOnBuiltInPanel(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'apply to portable panels only',
        );

        new Module(
            'debug',
            null,
            ['panels' => ['log' => ['class' => LogPanel::class, 'title' => 'Journal']]],
        );
    }

    public function testThrowInvalidConfigExceptionForPanelOptionWithUnsupportedValue(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage(
            "Debug panel option 'position' must be an integer.",
        );

        new Module(
            'debug',
            null,
            ['panels' => ['log' => ['class' => LogPanel::class, 'position' => 'first']]],
        );
    }

    public function testThrowInvalidConfigExceptionForPanelPositionRejectedByTheCatalog(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage(
            'Debug panel position must not be set on the built-in panel: log.',
        );

        new Module(
            'debug',
            null,
            ['panels' => ['log' => ['class' => LogPanel::class, 'position' => 1]]],
        );
    }

    public function testThrowInvalidConfigExceptionForUnknownArrayPanelClass(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'must declare a valid class name',
        );

        new Module(
            'debug',
            null,
            ['panels' => ['broken' => ['class' => 'No\\Such\\Class']]],
        );
    }

    public function testThrowInvalidConfigExceptionForUnknownStringPanelClass(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Module(
            'debug',
            null,
            ['panels' => ['broken' => 'No\\Such\\Panel']],
        );
    }

    public function testThrowInvalidConfigExceptionWhenContainerReturnsNonPortablePanelForPortableClass(): void
    {
        Yii::$container->set(CachePanel::class, static fn(): stdClass => new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANEL_INSTANCE_INVALID->getMessage('cache', PortablePanel::class, CachePanel::class),
        );

        new Module(
            'debug',
            null,
            ['panels' => ['cache' => CachePanel::class]],
        );
    }

    public function testThrowInvalidConfigExceptionWhenPanelRegistryIsRequestedBeforeInitialization(): void
    {
        $module = (new ReflectionClass(Module::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANELS_NOT_INITIALIZED->getMessage(),
        );

        $module->getPanelRegistry();
    }
}
