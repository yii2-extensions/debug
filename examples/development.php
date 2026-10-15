<?php

declare(strict_types=1);

use yii\debug\Module;

if (!YII_ENV_DEV) {
    return [];
}

return [
    'bootstrap' => ['debug'],
    'modules' => [
        'debug' => [
            'class' => Module::class,
            'allowedIPs' => ['127.0.0.1', '::1'],
            'historySize' => 50,
            'dataPath' => '@runtime/debug',
        ],
    ],
];
