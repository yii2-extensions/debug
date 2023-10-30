<?php

declare(strict_types=1);

use yii\debug\Module;

/**
 * @var array $params
 */
$debug = [];

if (isset($params['yii2.debug']) && $params['yii2.debug'] === true) {
    $debug = [
        // configuration adjustments for 'dev' environment
        'modules' => [
            'debug' => [
                'class' => Module::class,
                'allowedIPs' => $params['yii2.debug.allowedIPs'] ?? ['127.0.0.1', '::1'],
            ],
        ],
    ];
}

return $debug;
