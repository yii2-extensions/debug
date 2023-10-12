<?php

declare(strict_types=1);

return YII_ENV_DEV ?
    [
        // configuration adjustments for 'dev' environment
        'bootstrap' => ['debug'],
        'modules' => [
            'debug' => [
                'class' => \yii\debug\Module::class,
                // uncomment the following to add your IP if you are not connecting from localhost.
                // 'allowedIPs' => ['127.0.0.1', '::1'],
            ],
        ],
    ]
    : [];
