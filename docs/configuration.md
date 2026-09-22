# Configuration reference

Add only the options you need to the existing development-only module configuration:

```php
$config['modules']['debug']['historySize'] = 50;
$config['modules']['debug']['dataPath'] = '@runtime/debug';
$config['modules']['debug']['toolbarPosition'] = 'bottom';
```

These are the defaults: up to 50 retained requests, captures stored under `@runtime/debug`, and a bottom toolbar.
The storage path accepts a registered Yii alias.

## Custom collectors and panels

A collector implements `PHPForge\Debug\CollectorInterface` — the single contract shared by the built-in collectors,
the provider packages, and application-owned code — and returns the payload to persist from `capture()`:

```php
$config['modules']['debug']['collectors'][] = new Acme\Debug\CacheCollector();
$config['modules']['debug']['panels'][] = new Acme\Debug\CachePanel();
```

Collectors declare their own ID. Registering one under a string key requires that key to match
`CollectorInterface::id()`; a list entry needs no key at all. Built-in collectors extend
`yii\debug\collectors\Collector`, which builds a typed `PHPForge\Debug\Storage\PanelSnapshot` in `snapshot()`
and lets the base class encode it once.

An instance or a class string registers with the provider defaults. Use the array form to rename a panel, re-icon it,
place it among the other extensions, or leave it out:

```php
use PHPForge\Inertia\Debug\InertiaPanel;
use PHPForge\Vite\Debug\VitePanel;

$config['modules']['debug']['panels'] = [
    'vite' => ['class' => VitePanel::class, 'title' => 'Vite assets', 'icon' => 'asset', 'position' => 1],
    'inertia' => ['class' => InertiaPanel::class, 'enabled' => false],
    'cache' => Acme\Debug\CachePanel::class,
];
```

- `class` is required unless the entry is a class string or an instance; `title`, `icon`, `enabled`, and `position`
  are the only other accepted keys, and any other key is rejected by name.
- `enabled` set to `false` removes the entry before its class is resolved, so an uninstalled optional package is not
  an error. `collectors` entries accept the same flag.
- `position` orders an entry among the extensions, ascending; the entries without one follow, ordered by title. It is
  rejected on a built-in panel, and `title` and `icon` are rejected on any panel that is not provider-owned.
- The array key is the stable ID and must equal the provider's `id()`; a mismatch is rejected.

## Inertia and Vite

Both providers register themselves. Installing `php-forge/inertia` or `php-forge/vite` registers the collector and the
panel under the `inertia` or `vite` ID and builds the Inertia collector with its own redaction policy. Attachment is a
second, separate step: before the request runs the module hands the collector to the `inertia` component when
`yii2-extensions/inertia` is installed and the application configures an `inertia` component that is a
`yii\inertia\Manager` (or a subclass), and to the `vite` component when it is a `PHPForge\Vite\Vite` (or a subclass);
any other component is left alone and the panel simply records nothing. `php-forge/inertia` ships
`PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel}` and `php-forge/vite` ships
`PHPForge\Vite\Debug\{ViteCollector, VitePanel}`; the debugger lists both under **Extensions**.

A component that already configures its dispatcher, a closure definition, or a component the application instantiated
before the request keeps what it has. Disable either integration like any other entry:

```php
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

$config['modules']['debug']['collectors']['inertia'] = ['class' => InertiaCollector::class, 'enabled' => false];
$config['modules']['debug']['panels']['inertia'] = ['class' => InertiaPanel::class, 'enabled' => false];
$config['modules']['debug']['collectors']['vite'] = ['class' => ViteCollector::class, 'enabled' => false];
$config['modules']['debug']['panels']['vite'] = ['class' => VitePanel::class, 'enabled' => false];
```

Vite's **Production** label means it is inspecting built assets in a development application, not that the debugger
can run in production.

## Database

The debugger captures queries from the application's existing Yii2 database connections. Keep `enableProfiling`
enabled on those connections; Yii2 enables it by default.

Use the Database panel to filter queries, inspect duplicate and potential N+1 calls, and open EXPLAIN where supported.
Query timings also appear in Profiling. Keep the application's existing driver and connection settings.

## IDE links

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

## Module services

The module delegates its work to services under `yii\debug\service` and resolves each one through its own service
locator under the service class name. Replace a service by registering a definition under that name in the module
`components` configuration; the module passes itself as the `module` constructor argument, so a subclass keeps the
default constructor:

```php
use yii\debug\service\AccessGuard;

final class TeamAccessGuard extends AccessGuard
{
    public function allows(string $ip, \yii\base\Action|null $action = null): bool
    {
        return parent::allows($ip, $action) && \Yii::$app->user->can('debugger');
    }
}

$config['modules']['debug']['components'][AccessGuard::class] = TeamAccessGuard::class;
```

A definition may be a class name, a configuration array with `class`, a callable receiving `Module $module`, or an
instance. Register it before the module resolves the service: `CapturePolicyFactory`, `CollectorRegistrar`,
`PanelRegistrar`, and `StandaloneActionResolver` run during module initialization and `LogTargetFactory` during the
application bootstrap, so they must come from the configuration; `AccessGuard`, `ProviderCollectorAttacher`, and
`ToolbarPresenter` are resolved on the first request and may also be registered through `$module->set()` before it.

| Service | Responsibility |
|---|---|
| `AccessGuard` | Decides whether a request may reach the debugger from the IP, host, and callback rules. |
| `CapturePolicyFactory` | Builds the redaction and body-size policy applied to every capture. |
| `CollectorRegistrar` | Resolves the configured collectors into the coordinator driving the capture. |
| `LogTargetFactory` | Resolves the configured log target during the bootstrap. |
| `PanelRegistrar` | Resolves the configured panels and their display order. |
| `ProviderCollectorAttacher` | Hands each provider collector to the application component it observes. |
| `StandaloneActionResolver` | Merges the debugger action map and resolves routes against it. |
| `ToolbarPresenter` | Renders the toolbar and writes the debug response headers. |

## Standalone Router

Routing details appear in Request by default. To show a separate Router panel for an existing integration, opt in
within the development-only module configuration:

```php
$config['modules']['debug']['panels']['router'] = \yii\debug\panels\RouterPanel::class;
```

---

[← Back to documentation](../README.md#documentation)
