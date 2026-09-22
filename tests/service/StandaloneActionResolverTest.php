<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use yii\debug\actions\{IndexAction, PhpInfoAction, ViewAction};
use yii\debug\Module;
use yii\debug\service\StandaloneActionResolver;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\{ConfigurableAction, CustomPanel};

use function array_keys;

/**
 * Unit tests for {@see StandaloneActionResolver} merging the debugger action map and resolving routes against it.
 */
#[Group('service')]
final class StandaloneActionResolverTest extends ModuleTestCase
{
    public function testMapKeepsTheBuiltInsWhenNothingElseContributes(): void
    {
        self::assertSame(
            ['index' => IndexAction::class],
            $this->resolver()->map(['index' => IndexAction::class], [], []),
            'Built-ins must survive untouched.',
        );
    }

    public function testMapLetsConfiguredEntriesOverridePanelActions(): void
    {
        $panel = new CustomPanel();

        $panel->actions = ['db-explain' => PhpInfoAction::class];

        self::assertSame(
            ['db-explain' => ViewAction::class],
            $this->resolver()->map([], ['db' => $panel], ['db-explain' => ViewAction::class]),
            'Configured entries must win.',
        );
    }

    public function testMapLetsPanelActionsOverrideTheBuiltIns(): void
    {
        $panel = new CustomPanel();

        $panel->actions = ['index' => PhpInfoAction::class];

        self::assertSame(
            ['index' => PhpInfoAction::class],
            $this->resolver()->map(['index' => IndexAction::class], ['log' => $panel], []),
            'Panel actions must win over the built-ins.',
        );
    }

    public function testMapOrdersBuiltInsBeforePanelAndConfiguredActions(): void
    {
        $panel = new CustomPanel();

        $panel->actions = ['db-explain' => PhpInfoAction::class];

        self::assertSame(
            ['index', 'db-explain', 'custom'],
            array_keys(
                $this->resolver()->map(
                    ['index' => IndexAction::class],
                    ['db' => $panel],
                    ['custom' => ConfigurableAction::class],
                ),
            ),
            'Order: built-ins, panel actions, configured entries.',
        );
    }

    public function testResolveAppliesTheDefaultRouteToAnEmptyRoute(): void
    {
        $module = new Module('debug');

        $action = (new StandaloneActionResolver($module))->resolve('');

        self::assertInstanceOf(
            IndexAction::class,
            $action,
            'Module root must reach the default route.',
        );
        self::assertSame(
            'index',
            $action->id,
            'Resolved action must carry the canonical ID.',
        );
    }

    public function testResolveBindsTheResolvedActionToTheModule(): void
    {
        $module = new Module('debug');

        $module->actionMap['configured'] = [
            '__class' => ConfigurableAction::class,
            'label' => 'resolved',
        ];

        $action = (new StandaloneActionResolver($module))->resolve('configured');

        self::assertInstanceOf(
            ConfigurableAction::class,
            $action,
            "Yii '__class' configuration must resolve.",
        );
        self::assertSame(
            'configured',
            $action->id,
            'Resolved action must carry the requested ID.',
        );
        self::assertSame(
            'resolved',
            $action->label,
            'Configured properties must be preserved.',
        );
        self::assertSame(
            $module,
            $action->getModule(),
            'Resolved action must be bound to the module.',
        );
    }

    public function testResolveReturnsNullForAnUnmappedRoute(): void
    {
        self::assertNull(
            (new StandaloneActionResolver(new Module('debug')))->resolve('not-mapped'),
            'An unmapped route must fall through.',
        );
    }

    public function testResolveReturnsNullForARouteThatTrimsToNothing(): void
    {
        self::assertNull(
            (new StandaloneActionResolver(new Module('debug')))->resolve('/'),
            'A route of separators alone must fall through.',
        );
    }

    public function testResolveReturnsNullForNestedRoutes(): void
    {
        $module = new Module('debug');

        $module->actionMap['nested/action'] = PhpInfoAction::class;

        self::assertNull(
            (new StandaloneActionResolver($module))->resolve('nested/action'),
            'Multi-segment routes must fall through.',
        );
    }

    public function testResolveReturnsNullWhenTheMappedEntryIsNotAnAction(): void
    {
        $module = new Module('debug');

        $module->actionMap['foreign'] = stdClass::class;

        self::assertNull(
            (new StandaloneActionResolver($module))->resolve('foreign'),
            'An entry outside the action contract must fall through.',
        );
    }

    public function testResolveTrimsSurroundingSlashes(): void
    {
        $action = (new StandaloneActionResolver(new Module('debug')))->resolve('/view/');

        self::assertInstanceOf(
            ViewAction::class,
            $action,
            'Surrounding slashes must be ignored.',
        );
        self::assertSame(
            'view',
            $action->id,
            'Resolved ID must drop the surrounding slashes.',
        );
    }

    /**
     * Builds a resolver bound to a fresh debug module.
     */
    private function resolver(): StandaloneActionResolver
    {
        return new StandaloneActionResolver(new Module('debug'));
    }
}
