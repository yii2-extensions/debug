<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use yii\debug\models\router\ActionRoutes;
use yii\debug\tests\support\stub\router\controllers\{AbstractController, WebController};
use yii\debug\tests\support\stub\router\controllers\nested\NestedWebController;
use yii\debug\tests\support\stub\router\edge\controllers\EdgeCaseController;
use yii\debug\tests\support\stub\router\module\{MixedModulesStub, NullGetModuleStub};
use yii\debug\tests\support\TestCase;
use yii\web\{GroupUrlRule, UrlRule};

/**
 * Unit tests for {@see ActionRoutes} covering the controller scan that produces the action-to-route map shown in the
 * sRouter panel detail view.
 */
#[Group('router')]
final class ActionRoutesTest extends TestCase
{
    public function testExtensionMethodsRemainProtected(): void
    {
        self::assertSame(
            [true, true, true, true, true],
            array_map(
                static fn (string $method): bool => (new ReflectionMethod(ActionRoutes::class, $method))->isProtected(),
                [
                    'getActions',
                    'getAppRoutes',
                    'getMatchedCreationRule',
                    'getModuleControllers',
                    'validateControllerClass',
                ],
            ),
        );
    }

    public function testGetActionsReturnsOnlyPublicInstanceActionsAndExternalSentinel(): void
    {
        $this->mockWebApplication();

        self::assertSame(
            ['actionVisible', 'actionÁrbol', '__ACTIONS__'],
            $this->invoke(
                new ActionRoutes(),
                'getActions',
                [new ReflectionClass(EdgeCaseController::class)],
            ),
        );
    }

    public function testScanNormalizesAcronymAndUnicodeRouteIds(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\\debug\\tests\\support\\stub\\router\\edge\\controllers',
            ],
        );

        self::assertSame(
            [
                'yii\\debug\\tests\\support\\stub\\router\\edge\\controllers\\APIController::actionIndex()' => [
                    'count' => 0,
                    'route' => 'a-p-i/index',
                    'rule' => null,
                ],
                'yii\\debug\\tests\\support\\stub\\router\\edge\\controllers\\APIController::actions()' => [
                    'count' => 0,
                    'route' => 'a-p-i/[external-action]',
                    'rule' => null,
                ],
                'yii\\debug\\tests\\support\\stub\\router\\edge\\controllers\\EdgeCaseController::actionVisible()' => [
                    'count' => 0,
                    'route' => 'edge-case/visible',
                    'rule' => null,
                ],
                'yii\\debug\\tests\\support\\stub\\router\\edge\\controllers\\EdgeCaseController::actions()' => [
                    'count' => 0,
                    'route' => 'edge-case/[external-action]',
                    'rule' => null,
                ],
                'yii\\debug\\tests\\support\\stub\\router\\edge\\controllers\\EdgeCaseController::actionÁrbol()' => [
                    'count' => 0,
                    'route' => 'edge-case/árbol',
                    'rule' => null,
                ],
            ],
            (new ActionRoutes())->routes,
            'Acronym and Unicode route IDs must be normalized to kebab-case.',
        );
    }

    public function testValidateControllerClassAcceptsOnlyConcreteYiiControllers(): void
    {
        $this->mockWebApplication();

        $routes = new ActionRoutes();

        self::assertFalse(
            $this->invoke($routes, 'validateControllerClass', ['missing\\Controller']),
            'Missing controller classes must be rejected.',
        );
        self::assertFalse(
            $this->invoke($routes, 'validateControllerClass', [stdClass::class]),
            'Non-controller classes must be rejected.',
        );
        self::assertFalse(
            $this->invoke($routes, 'validateControllerClass', [AbstractController::class]),
            'Abstract controllers must be rejected.',
        );
        self::assertTrue(
            $this->invoke($routes, 'validateControllerClass', [WebController::class]),
            'Concrete Yii controllers must be accepted.',
        );
    }

    public function testScanLeavesTheMatchedRuleNullWhenItsNameIsNotAString(): void
    {
        MockerState::addCondition(
            'yii\\debug\\models\\router',
            'is_string',
            ['<controller>/<action>'],
            false,
        );

        $this->mockWebApplication(
            [
                'controllerMap' => ['mapped' => WebController::class],
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => ['<controller>/<action>' => '<controller>/<action>'],
                    ],
                ],
            ],
        );

        $routes = (new ActionRoutes())->routes;

        $first = $routes['yii\\debug\\tests\\support\\stub\\router\\controllers\\WebController::actionFirst()']
            ?? self::fail('Expected the mapped action to be scanned.');

        self::assertSame(
            1,
            $first['count'],
            'The rule still matches, so the counter advances.',
        );
        self::assertNull(
            $first['rule'],
            "A non-string rule name must surface as 'null'.",
        );
    }

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

        $routes = (new ActionRoutes())->routes;

        self::assertArrayNotHasKey(
            'yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()',
            $routes,
            "Failing 'preg_replace()' must skip the action via the defensive 'continue'.",
        );
        self::assertArrayHasKey(
            'yii\debug\tests\support\stub\router\controllers\WebController::actionSecond()',
            $routes,
            'A failed action normalization must not stop later actions from being discovered.',
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
                    'valid' => NestedWebController::class,
                ],
            ],
        );

        $routes = (new ActionRoutes())->routes;

        self::assertSame(
            [
                'yii\\debug\\tests\\support\\stub\\router\\controllers\\nested\\NestedWebController::actionShow()' => [
                    'count' => 1,
                    'route' => 'valid/show',
                    'rule' => '<controller>/<action>',
                ],
                'yii\\debug\\tests\\support\\stub\\router\\controllers\\nested\\NestedWebController::actions()' => [
                    'count' => 0,
                    'route' => 'valid/[external-action]',
                    'rule' => null,
                ],
            ],
            $routes,
            'Invalid controllerMap entries must be skipped without hiding later valid entries.',
        );
    }

    public function testScanSkipsControllerWhenActionsCountIsZero(): void
    {
        MockerState::addCondition(
            'yii\debug\models\router',
            'count',
            [['actionFirst', '__ACTIONS__', 'actionSecond']],
            0,
        );

        $this->mockWebApplication(
            [
                'controllerMap' => [
                    'empty' => WebController::class,
                    'mapped' => NestedWebController::class,
                ],
            ],
        );

        self::assertSame(
            [
                'yii\\debug\\tests\\support\\stub\\router\\controllers\\nested\\NestedWebController::actionShow()' => [
                    'count' => 0,
                    'route' => 'mapped/show',
                    'rule' => null,
                ],
                'yii\\debug\\tests\\support\\stub\\router\\controllers\\nested\\NestedWebController::actions()' => [
                    'count' => 0,
                    'route' => 'mapped/[external-action]',
                    'rule' => null,
                ],
            ],
            (new ActionRoutes())->routes,
            'A controller without actions must not stop later controllers from being scanned.',
        );
    }

    public function testScanContinuesPastInvalidAndMissingModuleEntries(): void
    {
        $this->mockWebApplication(['modules' => ['mixed' => MixedModulesStub::class]]);

        $routes = (new ActionRoutes())->routes;

        self::assertArrayHasKey(
            'yii\\debug\\tests\\support\\stub\\router\\controllers\\WebController::actionFirst()',
            $routes,
        );
        self::assertSame(
            'mixed/valid/mapped/first',
            $routes['yii\\debug\\tests\\support\\stub\\router\\controllers\\WebController::actionFirst()']['route'],
        );
    }

    public function testMatchedGroupRuleUsesFirstSuccessfulNestedRule(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'urlManager' => [
                        'enablePrettyUrl' => true,
                        'rules' => [
                            [
                                'class' => GroupUrlRule::class,
                                'rules' => [
                                    ['pattern' => 'first', 'route' => 'other/first', 'name' => 'first'],
                                    ['pattern' => 'target', 'route' => 'site/view', 'name' => 'target'],
                                    ['pattern' => 'last', 'route' => 'other/last', 'name' => 'last'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        self::assertSame(
            ['target', 1],
            $this->invoke(new ActionRoutes(), 'getMatchedCreationRule', ['site/view']),
            'The first matching rule must be returned, even if it is not the first rule in the group.',
        );
    }

    public function testScanSkipsFilesWithoutControllerSuffix(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\debug\tests\support\stub\router\controllers',
            ],
        );

        $routes = (new ActionRoutes())->routes;

        self::assertArrayHasKey(
            'yii\debug\tests\support\stub\router\controllers\WebController::actionFirst()',
            $routes,
            'Suffixed controllers must surface.',
        );
        self::assertArrayNotHasKey(
            'yii\debug\tests\support\stub\router\controllers\WebHelper::actionHidden()',
            $routes,
            'Filename guard must drop the non-suffixed class.',
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
