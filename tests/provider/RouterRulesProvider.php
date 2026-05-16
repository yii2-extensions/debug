<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\support\stub\CustomRuleStub;
use yii\web\UrlRule;

/**
 * Data provider for {@see \yii\debug\tests\support\router\RouterRulesTest} test cases.
 */
final class RouterRulesProvider
{
    /**
     * @return iterable<string, array{0: array<int|string, mixed>, 1: array<int, array<string, mixed>>}>
     */
    public static function webRulesCases(): iterable
    {
        yield 'creation only' => [
            [
                [
                    'pattern' => 'pattern',
                    'route' => 'route',
                    'mode' => UrlRule::CREATION_ONLY,
                ],
            ],
            [
                [
                    'mode' => 'creation only',
                    'name' => 'pattern',
                    'route' => 'route',
                    'suffix' => null,
                    'type' => null,
                    'verb' => null,
                ],
            ],
        ];
        yield 'parsing only' => [
            [
                [
                    'pattern' => 'pattern',
                    'route' => 'route',
                    'mode' => UrlRule::PARSING_ONLY,
                ],
            ],
            [
                [
                    'mode' => 'parsing only',
                    'name' => 'pattern',
                    'route' => 'route',
                    'suffix' => null,
                    'type' => null,
                    'verb' => null,
                ],
            ],
        ];
        yield 'custom' => [
            [['class' => CustomRuleStub::class]],
            [
                [
                    'mode' => null,
                    'name' => CustomRuleStub::class,
                    'route' => null,
                    'suffix' => null,
                    'type' => null,
                    'verb' => null,
                ],
            ],
        ];
        yield 'group' => [
            [
                [
                    'class' => 'yii\web\GroupUrlRule',
                    'prefix' => 'admin',
                    'rules' => [
                        'login' => 'user/login',
                        'logout' => 'user/logout',
                    ],
                ],
            ],
            [
                [
                    'mode' => null,
                    'name' => 'admin/login',
                    'route' => 'admin/user/login',
                    'suffix' => null,
                    'type' => 'GROUP',
                    'verb' => null,
                ],
                [
                    'mode' => null,
                    'name' => 'admin/logout',
                    'route' => 'admin/user/logout',
                    'suffix' => null,
                    'type' => 'GROUP',
                    'verb' => null,
                ],
            ],
        ];
        yield 'simple' => [
            ['rule' => 'route'],
            [
                [
                    'mode' => null,
                    'name' => 'rule',
                    'route' => 'route',
                    'suffix' => null,
                    'type' => null,
                    'verb' => null,
                ],
            ],
        ];
        yield 'simple verb' => [
            ['GET rule' => 'route'],
            [
                [
                    'mode' => null,
                    'name' => 'rule',
                    'route' => 'route',
                    'suffix' => null,
                    'type' => null,
                    'verb' => ['GET'],
                ],
            ],
        ];
        yield 'simple verb parse' => [
            ['POST rule' => 'route'],
            [
                [
                    'mode' => null,
                    'name' => 'rule',
                    'route' => 'route',
                    'suffix' => null,
                    'type' => null,
                    'verb' => ['POST'],
                ],
            ],
        ];
        yield 'suffix' => [
            [
                [
                    'pattern' => 'pattern',
                    'route' => 'route',
                    'suffix' => '.html',
                ],
            ],
            [
                [
                    'mode' => null,
                    'name' => 'pattern',
                    'route' => 'route',
                    'suffix' => '.html',
                    'type' => null,
                    'verb' => null,
                ],
            ],
        ];
        yield 'rest' => [
            [
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'user',
                ],
            ],
            [
                [
                    'mode' => null,
                    'name' => 'users/<id:\d[\d,]*>',
                    'route' => 'user/update',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => [
                        'PUT',
                        'PATCH',
                    ],
                ],
                [
                    'mode' => null,
                    'name' => 'users/<id:\d[\d,]*>',
                    'route' => 'user/delete',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => ['DELETE'],
                ],
                [
                    'mode' => null,
                    'name' => 'users/<id:\d[\d,]*>',
                    'route' => 'user/view',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => [
                        'GET',
                        'HEAD',
                    ],
                ],
                [
                    'mode' => null,
                    'name' => 'users',
                    'route' => 'user/create',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => ['POST'],
                ],
                [
                    'mode' => null,
                    'name' => 'users',
                    'route' => 'user/index',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => [
                        'GET',
                        'HEAD',
                    ],
                ],
                [
                    'mode' => null,
                    'name' => 'users/<id:\d[\d,]*>',
                    'route' => 'user/options',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => [],
                ],
                [
                    'mode' => null,
                    'name' => 'users',
                    'route' => 'user/options',
                    'suffix' => null,
                    'type' => 'REST',
                    'verb' => [],
                ],
            ],
        ];
        yield 'unknown mode' => [
            [
                [
                    'pattern' => 'pattern',
                    'route' => 'route',
                    'mode' => 999,
                ],
            ],
            [
                [
                    'mode' => 'unknown',
                    'name' => 'pattern',
                    'route' => 'route',
                    'suffix' => null,
                    'type' => null,
                    'verb' => null,
                ],
            ],
        ];
    }
}
