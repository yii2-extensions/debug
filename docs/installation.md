# Installation

The 0.2 development line requires PHP 8.3 or later, the `ctype`, `intl`, `mbstring` and `pdo` extensions, and
Yii 22.0. It does not support Yii 2.0.x. Keep the application's Yii 22 development stability setting scoped to
that dependency; do not lower the stability of every package just to install the debugger.

## Install the package

If the Yii 22 mailer is not already a root requirement, add both packages with scoped development flags:

```bash
composer require yii2-extensions/debug:^0.2@dev yiisoft/yii2-symfonymailer:^22.0@dev --dev
composer audit
```

If the application already requires `yiisoft/yii2-symfonymailer`, keep it in its existing dependency section and
ensure its constraint allows `^22.0@dev`; require only `yii2-extensions/debug:^0.2@dev` with `--dev`. The stability
flags must be declared in the application's root requirements; keep the global minimum stability unchanged.
Use the version that matches the application's framework and review [migration](migration.md) when moving from
0.1. Update the debugger and its
`php-forge/debug` and `php-forge/debug-core` dependencies together in the application's lockfile.

## Enable development mode

Set the environment before loading Yii in the application's Web entry script. Keep these values local to the
development environment:

```php
defined('YII_DEBUG') || define('YII_DEBUG', true);
defined('YII_ENV') || define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
```

Merge the [development configuration example](../examples/development.php) into the Web application's existing
configuration. Keep this registration out of shared and console configuration. Register both `bootstrap` and
`modules`; a module definition alone does not collect requests.
The example returns an empty array outside `YII_ENV_DEV`.

```php
$config = \yii\helpers\ArrayHelper::merge($config, require __DIR__ . '/debug.php');
```

Here `config/debug.php` is the example copied into the application. Keep the existing application components,
routes, database and authentication configuration. If another debugger already uses module ID `debug` or the
`yii\debug` namespace, remove that registration/package before enabling this one.

## Assets and storage

The Debug Core Composer package contains the compiled CSS, JavaScript and fonts. Yii publishes them through
`DebugAsset` and `ToolbarAsset` from `@vendor/php-forge/debug-core/resources/assets`. No npm asset alias, Foxy
plugin, JavaScript package manager or Node.js build is needed to consume this debugger. Foxy's native installer
adds no benefit to these packaged assets; leave the application's other frontend tooling as it is.

The Web process needs write access to its normal asset publication directory and to `@runtime/debug` (or the
configured `dataPath`). Keep captures outside the public Web root. The module defaults to directory mode `0700`
and file mode `0600`; use an application-specific ownership arrangement for multiple development workers.

## Verify the installation

Open a normal HTML application page that renders the Yii view body's end marker. The bottom toolbar should
appear and its chips should open the captured request. Open `debug/index` through the application's URL manager
to inspect History; non-pretty URLs may use `index.php?r=debug/index`.

The allowlist defaults to `127.0.0.1` and `::1`. In a container, verify the request IP Yii actually sees and add
only the trusted development address or narrow network needed. A callback adds a further restriction; it does
not override an IP denial. Do not disable the allowlist to diagnose a missing toolbar.

If no toolbar appears, check development registration, the view's `endBody()`, request IP, browser asset requests,
and runtime permissions. JSON responses do not have an HTML toolbar; inspect their debug response headers and
History instead. See [configuration](configuration.md) and [examples](examples.md).

Production entry scripts must not enable this module. Deploy production dependencies with
`composer install --no-dev`; `YII_ENV_DEV` must also be false so configuration never refers to an absent class.
