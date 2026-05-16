<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use yii\debug\models\router\ActionRoutes;
use yii\debug\tests\support\stub\router\controllers\nested\NestedWebController;
use yii\debug\tests\support\stub\router\controllers\WebController;
use yii\debug\tests\support\stub\router\module\NullGetModuleStub;
use yii\debug\tests\support\TestCase;
use yii\web\GroupUrlRule;

/**
 * Unit tests for {@see ActionRoutes} covering the controller scan that produces the action-to-route map shown in the
 * sRouter panel detail view.
 */
#[Group('router')]
final class ActionRoutesTest extends TestCase
{
    public function testScanResolvesControllerMapArrayConfigWithClassKey(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => ['<controller>/<action>' => '<controller>/<action>'],
                    ],
                ],
                'controllerMap' => ['mapped-class' => ['class' => WebController::class]],
            ],
        );

        $entry = (new ActionRoutes())
            ->routes['yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()'] ?? null;

        self::assertIsArray(
            $entry,
            "Mapped controller's action must surface.",
        );
        self::assertSame(
            'mapped-class/first',
            $entry['route'],
            "Array-shaped controllerMap entry with 'class' key must use its ID as the route prefix.",
        );
    }

    public function testScanResolvesControllerMapArrayConfigWithUnderscoreClassKey(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => ['<controller>/<action>' => '<controller>/<action>'],
                    ],
                ],
                'controllerMap' => ['mapped-uclass' => ['__class' => WebController::class]],
            ],
        );

        $entry = (new ActionRoutes())->routes
            ['yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()'] ?? null;

        self::assertIsArray(
            $entry,
            "Mapped controller's action must surface.",
        );
        self::assertSame(
            'mapped-uclass/first',
            $entry['route'],
            "Array-shaped controllerMap entry with '__class' key must use its ID as the route prefix.",
        );
    }

    public function testScanResolvesControllerMapStringConfig(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => ['<controller>/<action>' => '<controller>/<action>'],
                    ],
                ],
                'controllerMap' => ['mapped-string' => NestedWebController::class],
            ],
        );

        $entry = (new ActionRoutes())
            ->routes['yii\debug\tests\support\stub\router\controllers\nested\NestedWebController::actionShow()'] ?? null;

        self::assertIsArray(
            $entry,
            "String-mapped controller's action must surface.",
        );
        self::assertSame(
            'mapped-string/show',
            $entry['route'],
            'String controllerMap entries must use their ID as the route prefix.',
        );
    }

    public function testScanReturnsNoMatchedRuleWhenNoRulesAreConfigured(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\debug\tests\support\stub\router\controllers',
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => [],
                    ],
                ],
            ],
        );

        $routes = (new ActionRoutes())->routes;

        $webFirst = $routes['yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()'] ?? null;

        self::assertIsArray(
            $webFirst,
            "Web controller's first action must surface.",
        );
        self::assertNull(
            $webFirst['rule'],
            "No URL rules configured means the matched rule must be 'null'.",
        );
        self::assertSame(
            0,
            $webFirst['count'],
            'No URL rules configured means the scan counter must remain at zero.',
        );
    }

    public function testScansControllersAndModulesIntoRouteMap(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\debug\tests\support\stub\router\controllers',
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => [
                            '<controller>/<action>' => '<controller>/<action>',
                            [
                                'class' => GroupUrlRule::class,
                                'prefix' => 'admin',
                                'rules' => ['inside' => 'module-web/inside'],
                            ],
                        ],
                    ],
                ],
                'modules' => ['admin' => 'yii\debug\tests\support\stub\router\module\Module'],
            ],
        );

        $routes = new ActionRoutes();

        self::assertSame(
            [
                'yii\debug\tests\support\stub\router\controllers\BadController::actionOnly()' => [
                    'count' => 1, 'route' => 'bad/only', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\support\stub\router\controllers\BadController::actions()' => [
                    'count' => 0, 'route' => 'bad/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\support\stub\router\controllers\RedirectController::actionOnly()' => [
                    'count' => 1, 'route' => 'redirect/only', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\support\stub\router\controllers\RedirectController::actions()' => [
                    'count' => 0, 'route' => 'redirect/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\support\stub\router\controllers\RestController::actions()' => [
                    'count' => 0, 'route' => 'rest/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()' => [
                    'count' => 1, 'route' => 'web/first', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\support\stub\router\controllers\WebController::actionSecond()' => [
                    'count' => 1, 'route' => 'web/second', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\support\stub\router\controllers\WebController::actions()' => [
                    'count' => 0, 'route' => 'web/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\support\stub\router\controllers\nested\NestedWebController::actionShow()' => [
                    'count' => 2, 'route' => 'nested/nested-web/show', 'rule' => null,
                ],
                'yii\debug\tests\support\stub\router\controllers\nested\NestedWebController::actions()' => [
                    'count' => 0, 'route' => 'nested/nested-web/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\support\stub\router\module\controllers\ModuleWebController::actionInside()' => [
                    'count' => 2, 'route' => 'admin/module-web/inside', 'rule' => 'admin/inside',
                ],
                'yii\debug\tests\support\stub\router\module\controllers\ModuleWebController::actions()' => [
                    'count' => 0, 'route' => 'admin/module-web/[external-action]', 'rule' => null,
                ],
            ],
            $routes->routes,
            'ActionRoutes scan must return the documented per-action route map.',
        );
    }

    public function testScanSkipsActionWhenPregReplaceReturnsNull(): void
    {
        MockerState::addCondition(
            'yii\debug\models\router',
            'preg_replace',
            ['/\p{Lu}/u', '-\0', 'First'],
            null,
        );

        $this->mockWebApplication(
            [
                'controllerMap' => ['mapped' => WebController::class],
            ],
        );

        self::assertArrayNotHasKey(
            'yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()',
            (new ActionRoutes())->routes,
            "Failing 'preg_replace()' must skip the action via the defensive 'continue'.",
        );
    }

    public function testScanSkipsControllerMapEntriesWithInvalidShape(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\\not_a_real_namespace',
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => ['<controller>/<action>' => '<controller>/<action>'],
                    ],
                ],
                'controllerMap' => [
                    0 => WebController::class,
                    'bad-class' => stdClass::class,
                ],
            ],
        );

        $routes = (new ActionRoutes())->routes;

        self::assertSame(
            [],
            $routes,
            'Non-string controllerMap keys and non-Yii controller classes must be dropped, leaving no routes.',
        );
    }

    public function testScanSkipsControllerWhenActionsCountIsZero(): void
    {
        MockerState::addCondition('yii\debug\models\router', 'count', [], 0, true);

        $this->mockWebApplication(
            [
                'controllerMap' => ['mapped' => WebController::class],
            ],
        );

        self::assertSame(
            [],
            (new ActionRoutes())->routes,
            "Controllers reporting zero actions must be dropped via the defensive 'continue'.",
        );
    }

    public function testScanSkipsModuleEntryWhenGetModuleReturnsNull(): void
    {
        $this->mockWebApplication(
            [
                'modules' => ['weird' => NullGetModuleStub::class],
            ],
        );

        self::assertSame(
            [],
            (new ActionRoutes())->routes,
            "Child modules whose 'getModule()' returns 'null' must be skipped via the defensive 'continue'.",
        );
    }

    public function testScanSkipsModuleEntryWhenModuleIdIsNotString(): void
    {
        MockerState::addCondition('yii\debug\models\router', 'is_string', [], false, true);

        $this->mockWebApplication(
            [
                'modules' => ['admin' => 'yii\debug\tests\support\stub\router\module\Module'],
                'controllerMap' => ['mapped' => WebController::class],
            ],
        );

        self::assertSame(
            [],
            (new ActionRoutes())->routes,
            "Module ids that fail 'is_string()' must be skipped via the defensive 'continue'.",
        );
    }

    public function testScanSurfacesNestedControllersBelowControllerNamespace(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\debug\tests\support\stub\router\controllers',
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => ['<controller>/<action>' => '<controller>/<action>'],
                    ],
                ],
            ],
        );

        $routes = (new ActionRoutes())->routes;

        $nested = $routes['yii\debug\tests\support\stub\router\controllers\nested\NestedWebController::actionShow()'] ?? null;

        self::assertIsArray(
            $nested,
            'Nested controllers in subfolders must be discovered.',
        );
        self::assertSame(
            'nested/nested-web/show',
            $nested['route'],
            "Nested controllers must surface with the 'subdir/controller-id/action' route shape.",
        );
    }
}
