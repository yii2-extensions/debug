<?php

declare(strict_types=1);

/**
 * @var array $params
 */
if (isset($params['yii.debug']) && $params['yii.debug'] === true) {
    $debug = [
        // configuration adjustments for 'dev' environment
        'bootstrap' => ['debug'],
        'modules' => [
            'debug' => [
                'class' => \yii\debug\Module::class,
                // uncomment the following to add your IP if you are not connecting from localhost.
                // 'allowedIPs' => ['127.0.0.1', '::1'],
            ],
        ],
    ];
}
