# Usage examples

These examples extend an existing Yii 22 application. They do not create application routes, database credentials
or authentication. Install the debugger as described in [installation](installation.md).

## Development configuration

Create `config/debug.php` in the application with this configuration:

```php
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
```

Merge it before returning the application's configuration:

```php
$config = \yii\helpers\ArrayHelper::merge($config, require __DIR__ . '/debug.php');
```

The entry script must define `YII_ENV` before loading Yii. This configuration registers the module and bootstrap
only in development, retains 50 requests and restricts access to loopback addresses. Adjust the allowlist to the
actual trusted development client. Keep the application configuration in place when merging.

## Add an access condition

Inside the existing development guard, require a signed-in user as well as an allowed IP:

```php
$config['modules']['debug']['checkAccessCallback'] = static function (\yii\base\Action|null $action): bool {
    return !\Yii::$app->getUser()->getIsGuest();
};
```

This assumes a Web application with its existing `user` component. The callback receives the current action or
`null` and must return `true` to allow access. Preserve the application's own authorization rules.

## Inspect an application event

Write a development log entry from application code, request that page, then inspect Logs:

```php
\Yii::info(['operation' => 'catalog.preview', 'itemCount' => 3], 'app.catalog');
```

Logs can contain the original message and query diagnostics can contain values. Do not log credentials or
personal data just to test redaction. To extend exact-key redaction, preserve the default keys:

```php
use PHPForge\Debug\Helper\SensitiveDataRedactor;

$config['modules']['debug']['sensitiveKeys'] = [
    ...SensitiveDataRedactor::DEFAULT_KEYS,
    'tenant_signing_key',
];
```

## Inspect an existing Vite component

The Vite integration is optional. The application must install `php-forge/vite:^0.5` and already configure a Vite
component. Create `config/debug-vite.php` with this configuration, changing the
`dispatchers` value if the application's component has another ID:

```php
<?php

declare(strict_types=1);

use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

if (!YII_ENV_DEV) {
    return [];
}

return [
    'modules' => [
        'debug' => [
            'collectors' => ['vite' => ViteCollector::class],
            'panels' => ['vite' => VitePanel::class],
            'dispatchers' => ['vite' => 'vite'],
        ],
    ],
];
```

Merge it after the basic debug configuration:

```php
$config = \yii\helpers\ArrayHelper::merge($config, require __DIR__ . '/debug-vite.php');
```

Request an application page that uses that component. The Vite collector observes its events and the Vite panel
appears under Extensions. Registration alone does not build frontend assets or configure a Vite development
server. A preconfigured dispatcher is preserved; register the collector as its listener when the application
owns that dispatcher. For Inertia and policy-aware redaction, use the [provider configuration](configuration.md#provider-packages).

## Compare two requests

Make two application requests, open `debug/index`, select their History rows and compare them. The comparison
shows structural metrics and counts rather than exposing captured panel values. Open each capture for its
individual details. Requests with any `4xx` or `5xx` status are marked critical.

To show Router as a separate panel instead of only within Request, add this in the development guard:

```php
$config['modules']['debug']['panels']['router'] = \yii\debug\panels\RouterPanel::class;
```
