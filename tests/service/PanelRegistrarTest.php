<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use InvalidArgumentException;
use PHPForge\Debug\Panel as PortablePanel;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\{Module, Panel};
use yii\debug\panels\{LogPanel, ProviderPanel, QueuePanel};
use yii\debug\service\PanelRegistrar;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\cache\CachePanel;
use yii\debug\tests\support\stub\{ConfigurableAction, CustomPanel, ModuleBoundRecordingPanel};

/**
 * Unit tests for {@see PanelRegistrar} resolving panel definitions into the effective catalog.
 */
#[Group('service')]
final class PanelRegistrarTest extends ModuleTestCase
{
    public function testRegisterAcceptsAPanelInstanceAndBindsTheRegistrationKeyAsItsId(): void
    {
        $panel = new ModuleBoundRecordingPanel();
        $module = new Module('debug');

        $catalog = (new PanelRegistrar($module))->register([], ['log-instance' => $panel]);

        self::assertSame(
            'log-instance',
            $panel->id,
            'Registration key must bind as the panel ID.',
        );
        self::assertSame(
            $module,
            $panel->module,
            'Module reference must be bound.',
        );
        self::assertSame(
            1,
            $panel->moduleBoundCalls,
            'Binding hook must fire exactly once.',
        );
        self::assertSame(
            ['log-instance' => $panel],
            $catalog->panels,
            'Instance must be reused without rebuilding it.',
        );
    }

    public function testRegisterAdaptsAPortablePanelClassThroughTheProviderAdapter(): void
    {
        $catalog = $this->registrar()->register([], ['cache' => CachePanel::class]);

        $panel = $catalog->panels['cache'] ?? null;

        self::assertInstanceOf(
            ProviderPanel::class,
            $panel,
            'Portable class string must register through the adapter.',
        );
        self::assertSame(
            'Cache',
            $panel->getName(),
            'Provider title must apply unchanged.',
        );
        self::assertSame(
            'db',
            $panel->getToolbarIcon(),
            'Provider icon must apply unchanged.',
        );
    }

    public function testRegisterAppendsConfiguredPanelsAfterTheBuiltIns(): void
    {
        $catalog = $this->registrar()->register(
            ['log' => LogPanel::class],
            ['custom' => new CustomPanel()],
        );

        self::assertSame(
            ['log', 'custom'],
            array_keys($catalog->panels),
            'Configured entries must follow the built-ins.',
        );
    }

    public function testRegisterAppliesMetadataOverridesToAPortablePanel(): void
    {
        $catalog = $this->registrar()->register(
            [],
            ['cache' => ['class' => CachePanel::class, 'title' => 'Cache operations', 'icon' => 'asset']],
        );

        $panel = $catalog->panels['cache'] ?? null;

        self::assertInstanceOf(
            ProviderPanel::class,
            $panel,
            'Portable class definition must register through the adapter.',
        );
        self::assertSame(
            'Cache operations',
            $panel->getName(),
            'Configured title must win over the provider default.',
        );
        self::assertSame(
            'asset',
            $panel->getToolbarIcon(),
            'Configured icon must win over the provider default.',
        );
    }

    public function testRegisterDropsABuiltInWhoseOptionalPackageIsMissing(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $catalog = $this->registrar()->register(['queue' => QueuePanel::class], []);

        self::assertSame(
            [],
            $catalog->panels,
            'An unavailable integration must not register a panel.',
        );
    }

    public function testRegisterDropsAPanelThatReportsItselfDisabled(): void
    {
        $disabled = new class extends Panel {
            public function isEnabled(): bool
            {
                return false;
            }
        };

        $catalog = $this->registrar()->register([], ['ghost' => $disabled, 'custom' => new CustomPanel()]);

        self::assertArrayNotHasKey(
            'ghost',
            $catalog->panels,
            'A panel reporting itself disabled must be dropped.',
        );
        self::assertArrayHasKey(
            'custom',
            $catalog->panels,
            'A dropped panel must not stop later entries.',
        );
    }

    public function testRegisterOmitsAnIntegerKeyedDisabledEntryFromTheCatalog(): void
    {
        $catalog = $this->registrar()->register(
            [],
            [['class' => 'Acme\\Missing\\GhostPanel', 'enabled' => false]],
        );

        self::assertSame(
            [],
            $catalog->registry->disabled(),
            'Only a string key can be reported as disabled.',
        );
    }

    public function testRegisterPublishesEachPanelOnTheModuleBeforeTheNextOneBinds(): void
    {
        $observer = new class extends Panel {
            /**
             * @var list<string> Panel IDs already registered when this panel was bound.
             */
            public array $siblings = [];

            public function moduleBound(): void
            {
                $this->siblings = array_keys($this->module->panels ?? []);
            }
        };

        $this->registrar()->register([], ['first' => new CustomPanel(), 'second' => $observer]);

        self::assertSame(
            ['first'],
            $observer->siblings,
            'Panels bound earlier must already be visible.',
        );
    }

    public function testRegisterReportsAStringKeyedEntryDisabledByConfiguration(): void
    {
        $catalog = $this->registrar()->register(
            [],
            ['ghost' => ['class' => 'Acme\\Missing\\GhostPanel', 'enabled' => false]],
        );

        self::assertArrayNotHasKey(
            'ghost',
            $catalog->panels,
            'Disabled entry must stay unregistered.',
        );
        self::assertTrue(
            $catalog->registry->isDisabled('ghost'),
            'Disabled entry must be reported.',
        );
    }

    public function testThrowInvalidConfigExceptionForConfigurationWithoutResolvableClass(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANEL_CLASS_INVALID->getMessage('broken'),
        );

        $this->registrar()->register([], ['broken' => ['class' => 'Acme\\Missing\\GhostPanel']]);
    }

    public function testThrowInvalidConfigExceptionForMetadataOverrideOnAYiiPanel(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANEL_METADATA_OVERRIDE_UNSUPPORTED->getMessage('log'),
        );

        $this->registrar()->register([], ['log' => ['class' => LogPanel::class, 'title' => 'Journal']]);
    }

    public function testThrowInvalidConfigExceptionForMismatchedPortableRegistrationKey(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PROVIDER_ID_MISMATCH->getMessage('panel'),
        );

        $this->registrar()->register([], ['wrong' => ['class' => CachePanel::class]]);
    }

    public function testThrowInvalidConfigExceptionForObjectOutsideThePanelContract(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANEL_INSTANCE_INVALID->getMessage('not-panel', Panel::class, ConfigurableAction::class),
        );

        $this->registrar()->register([], ['not-panel' => ['class' => ConfigurableAction::class]]);
    }

    public function testThrowInvalidConfigExceptionForPanelOptionWithUnsupportedValue(): void
    {
        try {
            $this->registrar()->register([], ['log' => ['class' => LogPanel::class, 'position' => 'first']]);

            self::fail(
                'An unsupported registration option value must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                "Debug panel option 'position' must be an integer.",
                $exception->getMessage(),
                'Cause message must surface unchanged.',
            );
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

    public function testThrowInvalidConfigExceptionWhenAPositionRejectedByTheCatalogIsDeclared(): void
    {
        try {
            $this->registrar()->register([], ['log' => ['class' => LogPanel::class, 'position' => 1]]);

            self::fail(
                'A position on a built-in panel must be rejected.',
            );
        } catch (InvalidConfigException $exception) {
            self::assertSame(
                'Debug panel position must not be set on the built-in panel: log.',
                $exception->getMessage(),
                'Cause message must surface unchanged.',
            );
            self::assertInstanceOf(
                InvalidArgumentException::class,
                $exception->getPrevious(),
                'Original cause must be chained.',
            );
        }
    }

    public function testThrowInvalidConfigExceptionWhenTheContainerReturnsANonPortablePanel(): void
    {
        Yii::$container->set(CachePanel::class, static fn(): stdClass => new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANEL_INSTANCE_INVALID->getMessage('cache', PortablePanel::class, CachePanel::class),
        );

        $this->registrar()->register([], ['cache' => CachePanel::class]);
    }

    public function testThrowInvalidConfigExceptionWhenTwoEntriesResolveToTheSamePanelId(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PANEL_ID_DUPLICATE->getMessage('cache'),
        );

        $this->registrar()->register([], [new CachePanel(), new CachePanel()]);
    }

    private function registrar(): PanelRegistrar
    {
        return new PanelRegistrar(new Module('debug'));
    }
}
