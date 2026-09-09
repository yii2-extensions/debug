<!-- markdownlint-disable MD041 -->
<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_dark.svg">
        <source media="(prefers-color-scheme: light)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg">
        <img src="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg" alt="Yii Framework" width="80%">
    </picture>
    <h1 align="center">Debug</h1>
    <br>
</p>
<!-- markdownlint-enable MD041 -->

<p align="center">
    <a href="https://github.com/yii2-extensions/debug/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-extensions/debug/build.yml?style=for-the-badge&label=PHPUnit&logo=github" alt="PHPUnit">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/yii2-extensions/debug/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii2-extensions%2Fdebug%2Fmain" alt="Mutation Testing">
    </a>
    <a href="https://github.com/yii2-extensions/debug/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-extensions/debug/static.yml?style=for-the-badge&label=PHPStan&logo=github" alt="PHPStan">
    </a>
    <a href="https://github.com/yii2-extensions/debug/actions/workflows/security.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii2-extensions/debug/security.yml?style=for-the-badge&label=Security&logo=github" alt="Security">
    </a>
</p>

<p align="center">
    <strong>Debugger and toolbar for Yii2 applications</strong><br>
    <em>Pico-inspired UI, scoped CSS, light/dark mode, and 14 inspection panels</em>
</p>

<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/home-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/home-light.png">
    <img src="docs/images/home-light.png" alt="Debug toolbar">
</picture>

> [!WARNING]
> **Development only.** Never enable the debugger in production. Keep access restricted to trusted development IPs
> and install production dependencies with `composer install --no-dev`.

## Features

<picture>
    <source media="(min-width: 768px)" srcset="./docs/svgs/features.svg">
    <img src="./docs/svgs/features-mobile.svg" alt="Feature Overview" style="width: 100%;">
</picture>

## Quick start

### Installation

Requires PHP 8.3 or newer and Yii2 22.x. The 0.2 line does not support Yii2 2.0.x.

```bash
composer require yii2-extensions/debug:^0.2 --dev
```

When upgrading from 0.1, review the [changelog](CHANGELOG.md) for breaking changes. Keep the debugger and
`php-forge/debug-core` up to date together in the application's lock file.

### Enable the debugger

In your application's entry script, set `YII_ENV` to `dev` before loading Yii. Then register the module in
`config/web.php`, before returning `$config`:

```php
if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}
```

Keep the registration inside the development guard. Do not bootstrap the module in production.

### Basic usage

Open an application page, expand the toolbar at the bottom, and select a panel chip to inspect the request.
Use the Yii chip for Configuration and the PHP chip for PHP info. Switch between light and dark themes from the
toolbar, and press `Escape` to close the drawer.

Open the `debug/index` route to browse retained requests. Select two captures in History to compare request metrics
and panel changes, then open either capture for its details. Comparison shows structural counts without exposing
panel values.

## Configuration

Add only the options you need to the existing development-only module configuration:

```php
$config['modules']['debug']['historySize'] = 50;
$config['modules']['debug']['dataPath'] = '@runtime/debug';
$config['modules']['debug']['toolbarPosition'] = 'bottom';
```

These are the defaults: up to 50 retained requests, captures stored under `@runtime/debug`, and a bottom toolbar.
The storage path accepts a registered Yii alias.

### Inertia and Vite

The built-in integrations use the application's existing services; no custom panel registration is needed.
For Inertia, configure the `yii2-extensions/inertia` manager as the `inertia` application component. Vite inspects
supported services already loaded during the request, including `PHPForge\Vite\Vite` and `yii\inertia\Vite`.

An integration's data depends on the selected capture. Vite's **Production** label means it is inspecting built
assets in a development application, not that the debugger can run in production.

### Database

The debugger captures queries from the application's existing Yii2 database connections. Keep `enableProfiling`
enabled on those connections; Yii2 enables it by default.

Use the Database panel to filter queries, inspect duplicate and potential N+1 calls, and open EXPLAIN where supported.
Query timings also appear in Profiling. Keep the application's existing driver and connection settings.

### IDE links

Source traces use `ide://` links by default. To use another editor, set its URL template in the existing module
configuration:

```php
$config['modules']['debug']['traceLine'] = '<a href="phpstorm://open?file={file}&line={line}">{text}</a>';
```

For containers or remote environments, map captured paths to your local project:

```php
$config['modules']['debug']['tracePathMappings'] = [
    '/var/www/html' => '/home/developer/projects/app',
];
```

### Standalone Router

Routing details appear in Request by default. To show a separate Router panel for an existing integration, opt in
within the development-only module configuration:

```php
$config['modules']['debug']['panels']['router'] = \yii\debug\panels\RouterPanel::class;
```

## Security

The toolbar and debugger routes allow `127.0.0.1` and `::1` by default. Add only trusted development addresses to
`allowedIPs`; never expose the debugger publicly. A `checkAccessCallback` can further restrict allowed requests.

Request, identity, queue, and Inertia captures redact common sensitive fields. Logs preserve original diagnostic
values and are not redacted by the capture policy; SQL diagnostics can include substituted query values. Treat
stored captures as sensitive and review them before sharing.

In the Events panel, context capture and source traces are disabled by default. This does not disable source
traces in Logs or Database.

To redact additional exact keys, extend the defaults instead of replacing them:

```php
use PHPForge\Debug\Helper\SensitiveDataRedactor;

$config['modules']['debug']['sensitiveKeys'] = [
    ...SensitiveDataRedactor::DEFAULT_KEYS,
    'tenant_signing_key',
];
```

## Browser support

Use a current browser with Web Components, native JavaScript modules, and CSS custom properties. Internet Explorer
is not supported.

## Screenshots

<details>
<summary>History</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/history-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/history-light.png">
    <img src="docs/images/history-light.png" alt="History panel">
</picture>
</details>

<details>
<summary>Request</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/request-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/request-light.png">
    <img src="docs/images/request-light.png" alt="Request panel">
</picture>
</details>

<details>
<summary>Logs</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/log-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/log-light.png">
    <img src="docs/images/log-light.png" alt="Logs panel">
</picture>
</details>

<details>
<summary>Events</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/event-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/event-light.png">
    <img src="docs/images/event-light.png" alt="Events panel">
</picture>
</details>

<details>
<summary>Profiling</summary>

Profiling combines the request-relative Timeline and sortable span details under one shared set of filters.
Timeline labels show only the short class name, such as `HomeAction`; hover a label to inspect its full FQCN and
method, which also remain visible in Details.

<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/profiling-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/profiling-light.png">
    <img src="docs/images/profiling-light.png" alt="Profiling panel">
</picture>
</details>

<details>
<summary>Database</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/database-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/database-light.png">
    <img src="docs/images/database-light.png" alt="Database panel">
</picture>
</details>

<details>
<summary>Configuration</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/config-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/config-light.png">
    <img src="docs/images/config-light.png" alt="Configuration panel">
</picture>
</details>

<details>
<summary>PHP info</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/phpinfo-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/phpinfo-light.png">
    <img src="docs/images/phpinfo-light.png" alt="PHP info panel">
</picture>
</details>

<details>
<summary>Router (standalone)</summary>
<p>The same captured routing trace is shown in Request by default. This screen remains available when Router is
configured explicitly for compatibility.</p>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/router-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/router-light.png">
    <img src="docs/images/router-light.png" alt="Router panel">
</picture>
</details>

<details>
<summary>Inertia</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/inertia-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/inertia-light.png">
    <img src="docs/images/inertia-light.png" alt="Inertia panel">
</picture>
</details>

<details>
<summary>Mail</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/mail-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/mail-light.png">
    <img src="docs/images/mail-light.png" alt="Mail panel">
</picture>
</details>

<details>
<summary>Queue</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/queue-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/queue-light.png">
    <img src="docs/images/queue-light.png" alt="Queue panel">
</picture>
</details>

<details>
<summary>Queue job</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/queue-job-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/queue-job-light.png">
    <img src="docs/images/queue-job-light.png" alt="Queue job detail">
</picture>
</details>

<details>
<summary>Dump</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/dump-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/dump-light.png">
    <img src="docs/images/dump-light.png" alt="Dump panel">
</picture>
</details>

<details>
<summary>Asset bundles</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/asset-bundles-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/asset-bundles-light.png">
    <img src="docs/images/asset-bundles-light.png" alt="Asset bundles panel">
</picture>
</details>

<details>
<summary>User</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/user-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/user-light.png">
    <img src="docs/images/user-light.png" alt="User panel">
</picture>
</details>

<details>
<summary>User Roles and Permissions</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/user-roles-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/user-roles-light.png">
    <img src="docs/images/user-roles-light.png" alt="User panel — Roles and Permissions">
</picture>
</details>

<details>
<summary>User Switch User</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/user-switch-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/user-switch-light.png">
    <img src="docs/images/user-switch-light.png" alt="User panel — Switch User">
</picture>
</details>

## Documentation

- [Testing guide](docs/testing.md)
- [Changelog](CHANGELOG.md)

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![Yii 22.0.x](https://img.shields.io/badge/22.0.x-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white)](https://github.com/yiisoft/yii2/tree/22.0)
[![Latest Stable Version](https://img.shields.io/packagist/v/yii2-extensions/debug.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Stable)](https://packagist.org/packages/yii2-extensions/debug)
[![Total Downloads](https://img.shields.io/packagist/dt/yii2-extensions/debug.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/yii2-extensions/debug)

## Project status

[![Codecov](https://img.shields.io/codecov/c/github/yii2-extensions/debug.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/github/yii2-extensions/debug)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/yii2-extensions/debug/actions/workflows/static.yml)
[![Quality](https://img.shields.io/github/actions/workflow/status/yii2-extensions/debug/quality.yml?style=for-the-badge&label=Quality&logo=github)](https://github.com/yii2-extensions/debug/actions/workflows/quality.yml)
[![StyleCI](https://img.shields.io/badge/StyleCI-Passed-44CC11.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.styleci.io/repos/699842423?branch=main)

## Our social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)
[![Follow on Facebook](https://img.shields.io/badge/-Follow%20on%20Facebook-1877F2.svg?style=for-the-badge&logo=facebook&logoColor=white&labelColor=000000)](https://www.facebook.com/wilmer.arambula.9)
[![Join our Subreddit](https://img.shields.io/badge/-Join%20our%20Subreddit-FF4500.svg?style=for-the-badge&logo=reddit&logoColor=white&labelColor=000000)](https://www.reddit.com/r/Yii2/)
[![Join on Telegram](https://img.shields.io/badge/-Join%20on%20Telegram-26A5E4.svg?style=for-the-badge&logo=telegram&logoColor=white&labelColor=000000)](https://t.me/yii_framework_in_english)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)
