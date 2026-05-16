<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\router\ActionRoutes;
use yii\debug\tests\support\TestCase;
use yii\web\GroupUrlRule;

/**
 * Unit tests for {@see ActionRoutes} covering the controller scan that produces the action-to-route map shown in the
 * sRouter panel detail view.
 */
#[Group('router')]
final class ActionRoutesTest extends TestCase
{
    public function testScansControllersAndModulesIntoRouteMap(): void
    {
        $this->mockWebApplication(
            [
                'controllerNamespace' => 'yii\debug\tests\router\controllers',
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
                'modules' => ['admin' => 'yii\debug\tests\router\module\Module'],
            ],
        );

        $routes = new ActionRoutes();

        self::assertSame(
            [
                'yii\debug\tests\router\controllers\BadController::actionOnly()' => [
                    'count' => 1, 'route' => 'bad/only', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\router\controllers\BadController::actions()' => [
                    'count' => 0, 'route' => 'bad/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\router\controllers\RedirectController::actionOnly()' => [
                    'count' => 1, 'route' => 'redirect/only', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\router\controllers\RedirectController::actions()' => [
                    'count' => 0, 'route' => 'redirect/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\router\controllers\RestController::actions()' => [
                    'count' => 0, 'route' => 'rest/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\router\controllers\WebController::actionFirst()' => [
                    'count' => 1, 'route' => 'web/first', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\router\controllers\WebController::actionSecond()' => [
                    'count' => 1, 'route' => 'web/second', 'rule' => '<controller>/<action>',
                ],
                'yii\debug\tests\router\controllers\WebController::actions()' => [
                    'count' => 0, 'route' => 'web/[external-action]', 'rule' => null,
                ],
                'yii\debug\tests\router\module\controllers\ModuleWebController::actionInside()' => [
                    'count' => 2, 'route' => 'admin/module-web/inside', 'rule' => 'admin/inside',
                ],
                'yii\debug\tests\router\module\controllers\ModuleWebController::actions()' => [
                    'count' => 0, 'route' => 'admin/module-web/[external-action]', 'rule' => null,
                ],
            ],
            $routes->routes,
            'ActionRoutes scan must return the documented per-action route map.',
        );
    }
}
