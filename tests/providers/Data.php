<?php

declare(strict_types=1);

namespace yiiunit\debug\providers;

use yii\web\UrlRule;

class Data
{
    public static function checkAccess(): array
    {
        return [
            [
                [],
                '10.20.30.40',
                false,
            ],
            [
                ['10.20.30.40'],
                '10.20.30.40',
                true,
            ],
            [
                ['*'],
                '10.20.30.40',
                true,
            ],
            [
                ['10.20.30.*'],
                '10.20.30.40',
                true,
            ],
            [
                ['10.20.30.*'],
                '10.20.40.40',
                false,
            ],
            [
                ['172.16.0.0/12'],
                '172.15.1.2', // "below" CIDR range
                false,
            ],
            [
                ['172.16.0.0/12'],
                '172.16.0.0', // in CIDR range
                true,
            ],
            [
                ['172.16.0.0/12'],
                '172.22.33.44', // in CIDR range
                true,
            ],
            [
                ['172.16.0.0/12'],
                '172.31.255.255', // in CIDR range
                true,
            ],
            [
                ['172.16.0.0/12'],
                '172.32.1.2',  // "above" CIDR range
                false,
            ],
        ];
    }

    public static function forWebRules(): array
    {
        return [
            'simple' => [
                ['rule' => 'route'],
                [[
                    'name' => 'rule',
                    'route' => 'route',
                    'verb' => null,
                    'suffix' => null,
                    'mode' => null,
                    'type' => null,
                ]],
            ],
            'simple verb' => [
                ['GET rule' => 'route'],
                [[
                    'name' => 'rule',
                    'route' => 'route',
                    'verb' => ['GET'],
                    'suffix' => null,
                    'mode' => null,
                    'type' => null,
                ]],
            ],
            'simple verb parse' => [
                ['POST rule' => 'route'],
                [[
                    'name' => 'rule',
                    'route' => 'route',
                    'verb' => ['POST'],
                    'suffix' => null,
                    'mode' => null,
                    'type' => null,
                ]],
            ],
            'custom' => [
                [['class' => 'yiiunit\debug\router\CustomRuleStub']],
                [[
                    'name' => 'yiiunit\debug\router\CustomRuleStub',
                    'route' => null,
                    'verb' => null,
                    'suffix' => null,
                    'mode' => null,
                    'type' => null,
                ]],
            ],
            'creation only' => [
                [['pattern' => 'pattern', 'route' => 'route', 'mode' => UrlRule::CREATION_ONLY]],
                [[
                    'name' => 'pattern',
                    'route' => 'route',
                    'verb' => null,
                    'suffix' => null,
                    'mode' => 'creation only',
                    'type' => null,
                ]],
            ],
            'unknown mode' => [
                [['pattern' => 'pattern', 'route' => 'route', 'mode' => 999]],
                [[
                    'name' => 'pattern',
                    'route' => 'route',
                    'verb' => null,
                    'suffix' => null,
                    'mode' => 'unknown',
                    'type' => null,
                ]],
            ],
            'suffix' => [
                [['pattern' => 'pattern', 'route' => 'route', 'suffix' => '.html']],
                [[
                    'name' => 'pattern',
                    'route' => 'route',
                    'verb' => null,
                    'suffix' => '.html',
                    'mode' => null,
                    'type' => null,
                ]],
            ],
            'group' => [
                [[
                    'class' => 'yii\web\GroupUrlRule',
                    'prefix' => 'admin',
                    'rules' => [
                        'login' => 'user/login',
                        'logout' => 'user/logout',
                    ],
                ]],
                [
                    [
                        'name' => 'admin/login',
                        'route' => 'admin/user/login',
                        'verb' => null,
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'GROUP',
                    ],
                    [
                        'name' => 'admin/logout',
                        'route' => 'admin/user/logout',
                        'verb' => null,
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'GROUP',
                    ],
                ],
            ],
            'rest' => [
                [['class' => 'yii\rest\UrlRule', 'controller' => 'user']],
                [
                    [
                        'name' => 'users/<id:\d[\d,]*>',
                        'route' => 'user/update',
                        'verb' => ['PUT', 'PATCH'],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                    [
                        'name' => 'users/<id:\d[\d,]*>',
                        'route' => 'user/delete',
                        'verb' => ['DELETE'],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                    [
                        'name' => 'users/<id:\d[\d,]*>',
                        'route' => 'user/view',
                        'verb' => ['GET', 'HEAD'],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                    [
                        'name' => 'users',
                        'route' => 'user/create',
                        'verb' => ['POST'],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                    [
                        'name' => 'users',
                        'route' => 'user/index',
                        'verb' => ['GET', 'HEAD'],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                    [
                        'name' => 'users/<id:\d[\d,]*>',
                        'route' => 'user/options',
                        'verb' => [],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                    [
                        'name' => 'users',
                        'route' => 'user/options',
                        'verb' => [],
                        'suffix' => null,
                        'mode' => null,
                        'type' => 'REST',
                    ],
                ],
            ],
        ];
    }
}
