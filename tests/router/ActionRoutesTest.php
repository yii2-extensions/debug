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
                    'route' => 'bad/only', 'rule' => '<controller>/<action>', 'count' => 1,
                ],
                'yiiunit\debug\router\controllers\BadController::actions()' => [
                    'route' => 'bad/[external-action]', 'rule' => null, 'count' => 0,
                ],
                'yiiunit\debug\router\controllers\RedirectController::actionOnly()' => [
                    'route' => 'redirect/only', 'rule' => '<controller>/<action>', 'count' => 1,
                ],
                'yiiunit\debug\router\controllers\RedirectController::actions()' => [
                    'route' => 'redirect/[external-action]', 'rule' => null, 'count' => 0,
                ],
                'yiiunit\debug\router\controllers\RestController::actions()' => [
                    'route' => 'rest/[external-action]', 'rule' => null, 'count' => 0,
                ],
                'yiiunit\debug\router\controllers\WebController::actionFirst()' => [
                    'route' => 'web/first', 'rule' => '<controller>/<action>', 'count' => 1,
                ],
                'yiiunit\debug\router\controllers\WebController::actionSecond()' => [
                    'route' => 'web/second', 'rule' => '<controller>/<action>', 'count' => 1,
                ],
                'yiiunit\debug\router\controllers\WebController::actions()' => [
                    'route' => 'web/[external-action]', 'rule' => null, 'count' => 0,
                ],
                'yiiunit\debug\router\module\controllers\ModuleWebController::actionInside()' => [
                    'route' => 'admin/module-web/inside', 'rule' => 'admin/inside', 'count' => 2,
                ],
                'yiiunit\debug\router\module\controllers\ModuleWebController::actions()' => [
                    'route' => 'admin/module-web/[external-action]', 'rule' => null, 'count' => 0,
                ],
            ],
            $routes->routes,
            'ActionRoutes scan must return the documented per-action route map.',
        );
    }
}
