<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\debug\{Module, Panel};
use yii\debug\panels\{DbPanel, LogPanel};
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\{ConfigurableAction, CustomDbPanel, ModuleBoundRecordingPanel};

/**
 * Unit tests for {@see Module} covering panel class/configuration/instance resolution, invalid and disabled panels,
 * `moduleBound` invocation, override ordering, and service-locator registration by concrete and ancestor classes.
 */
#[Group('module')]
final class ModulePanelRegistrationTest extends ModuleTestCase
{
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

    public function testInitPanelsContinuesAfterAnInvalidCustomPanel(): void
    {
        $valid = new LogPanel();
        $module = new Module(
            'debug',
            null,
            ['panels' => ['broken' => ['class' => 'No\\Such\\Class'], 'after-broken' => $valid]],
        );

        self::assertSame(
            $valid,
            $module->panels['after-broken'] ?? null,
            'An invalid panel must not prevent later configured panels from loading.',
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

    public function testInitPanelsDropsResolvedObjectsThatAreNotPanels(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['not-panel' => ['class' => ConfigurableAction::class]]],
        );

        self::assertArrayNotHasKey(
            'not-panel',
            $module->panels,
            'Resolved objects that do not implement the panel contract must be dropped.',
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

        $lastId = array_key_last($module->panels);

        self::assertSame('log', $lastId, 'Override must move the panel to its configured slot.');
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

    public function testInitPanelsRejectsUnknownStringPanelClass(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Module(
            'debug',
            null,
            ['panels' => ['broken' => 'No\\Such\\Panel']],
        );
    }

    public function testInitPanelsReturnsNullWhenConfigClassIsInvalid(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['broken' => ['class' => 'No\\Such\\Class']]],
        );

        self::assertArrayNotHasKey(
            'broken',
            $module->panels,
            'Panel configs with an unloadable class must be dropped silently.',
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
}
