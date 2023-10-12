<?php

declare(strict_types=1);

/**
 * @var array $params
 */
$debug = [];

if (isset($params['yii.debug']) && $params['yii.debug'] === true) {
    $debug = [
        // configuration adjustments for 'dev' environment
        'bootstrap' => ['debug'],
        'modules' => [
            'debug' => [
                'class' => \yii\debug\Module::class,
                'allowedIPs' => $params['yii.debug.allowedIPs'] ?? [],
            ],
        ],
    ];
}

return $debug;
