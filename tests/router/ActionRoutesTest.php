<?php

declare(strict_types=1);

namespace yiiunit\debug\router;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\router\ActionRoutes;
use yiiunit\debug\TestCase;

/**
 * Unit tests for {@see ActionRoutes} covering the controller scan that produces the
 * action-to-route map shown in the Router panel detail view.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 2.1.29
 */
#[Group('router')]
final class ActionRoutesTest extends TestCase
{
    public function testScansControllersAndModulesIntoRouteMap(): void
    {
        $this->mockWebApplication([
            'controllerNamespace' => 'yiiunit\debug\router\controllers',
            'components' => [
                'urlManager' => [
                    'enablePrettyUrl' => true,
                    'rules' => [
                        '<controller>/<action>' => '<controller>/<action>',
                        [
                            'class' => 'yii\web\GroupUrlRule',
                            'prefix' => 'admin',
                            'rules' => ['inside' => 'module-web/inside'],
                        ],
                    ],
                ],
            ],
            'modules' => ['admin' => 'yiiunit\debug\router\module\Module'],
        ]);

        $routes = new ActionRoutes();

        self::assertSame(
            [
                'yiiunit\debug\router\controllers\BadController::actionOnly()' => [
                    'count' => 1, 'route' => 'bad/only', 'rule' => '<controller>/<action>',
                ],
                'yiiunit\debug\router\controllers\BadController::actions()' => [
                    'count' => 0, 'route' => 'bad/[external-action]', 'rule' => null,
                ],
                'yiiunit\debug\router\controllers\RedirectController::actionOnly()' => [
                    'count' => 1, 'route' => 'redirect/only', 'rule' => '<controller>/<action>',
                ],
                'yiiunit\debug\router\controllers\RedirectController::actions()' => [
                    'count' => 0, 'route' => 'redirect/[external-action]', 'rule' => null,
                ],
                'yiiunit\debug\router\controllers\RestController::actions()' => [
                    'count' => 0, 'route' => 'rest/[external-action]', 'rule' => null,
                ],
                'yiiunit\debug\router\controllers\WebController::actionFirst()' => [
                    'count' => 1, 'route' => 'web/first', 'rule' => '<controller>/<action>',
                ],
                'yiiunit\debug\router\controllers\WebController::actionSecond()' => [
                    'count' => 1, 'route' => 'web/second', 'rule' => '<controller>/<action>',
                ],
                'yiiunit\debug\router\controllers\WebController::actions()' => [
                    'count' => 0, 'route' => 'web/[external-action]', 'rule' => null,
                ],
                'yiiunit\debug\router\module\controllers\ModuleWebController::actionInside()' => [
                    'count' => 2, 'route' => 'admin/module-web/inside', 'rule' => 'admin/inside',
                ],
                'yiiunit\debug\router\module\controllers\ModuleWebController::actions()' => [
                    'count' => 0, 'route' => 'admin/module-web/[external-action]', 'rule' => null,
                ],
            ],
            $routes->routes,
            'ActionRoutes scan must return the documented per-action route map.',
        );
    }
}
