<?php

declare(strict_types=1);

use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

if (!YII_ENV_DEV) {
    return [];
}

// Merge after development.php; the application must already define its vite component.
return [
    'modules' => [
        'debug' => [
            'collectors' => ['vite' => ViteCollector::class],
            'panels' => ['vite' => VitePanel::class],
            'dispatchers' => ['vite' => 'vite'],
        ],
    ],
];
