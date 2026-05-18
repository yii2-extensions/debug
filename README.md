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

## Features

<picture>
    <source media="(min-width: 768px)" srcset="./docs/svgs/features.svg">
    <img src="./docs/svgs/features-mobile.svg" alt="Feature Overview" style="width: 100%;">
</picture>

## Quick start

### Installation

```bash
composer require yii2-extensions/debug:^0.1 --dev
```

### Basic Usage

Enable the debug module in your application configuration (`config/web.php`).

```php
if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}
```

The toolbar appears at the bottom of every rendered page; click any panel chip to open the full debugger.

## Screenshots

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
<summary>Router</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/router-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/router-light.png">
    <img src="docs/images/router-light.png" alt="Router panel">
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
<summary>Database</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/database-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/database-light.png">
    <img src="docs/images/database-light.png" alt="Database panel">
</picture>
</details>

<details>
<summary>Profiling</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/profiling-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/profiling-light.png">
    <img src="docs/images/profiling-light.png" alt="Profiling panel">
</picture>
</details>

<details>
<summary>Timeline</summary>
<picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/timeline-dark.png">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/timeline-light.png">
    <img src="docs/images/timeline-light.png" alt="Timeline panel">
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

For detailed configuration options and advanced usage.

- 🧪 [Testing Guide](docs/testing.md)

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![Yii 2.0.55](https://img.shields.io/badge/2.0.55-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white)](https://github.com/yiisoft/yii2)
[![Yii 22.0.x](https://img.shields.io/badge/22.0-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white)](https://github.com/yiisoft/yii2/tree/22.0)
[![Latest Stable Version](https://img.shields.io/packagist/v/yii2-extensions/debug.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Stable)](https://packagist.org/packages/yii2-extensions/debug)
[![Total Downloads](https://img.shields.io/packagist/dt/yii2-extensions/debug.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/yii2-extensions/debug)

## Quality code

[![Codecov](https://img.shields.io/codecov/c/github/yii2-extensions/debug.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/github/yii2-extensions/debug)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/yii2-extensions/debug/actions/workflows/static.yml)
[![Super-Linter](https://img.shields.io/github/actions/workflow/status/yii2-extensions/debug/linter.yml?style=for-the-badge&label=Super-Linter&logo=github)](https://github.com/yii2-extensions/debug/actions/workflows/linter.yml)
[![StyleCI](https://img.shields.io/badge/StyleCI-Passed-44CC11.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.styleci.io/repos/yii2-extensions/debug?branch=main)

## Our social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)
