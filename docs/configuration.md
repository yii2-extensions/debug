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

## Provider packages

Provider packages register exactly like the `cache` example above: a collector and a panel under the same stable ID.
The module names no provider and detects none; nothing is registered until the application declares it. When the
package emits its results through a PSR-14 dispatcher, `dispatchers` hands the collector to the application component
that emits them, `collectorId => componentId`.

Inside the `YII_DEBUG` guard of the development configuration:

```php
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

$config['modules']['debug'] = [
    'class' => \yii\debug\Module::class,
    'collectors' => [
        'inertia' => static fn(CapturePolicy $policy): InertiaCollector
            => new InertiaCollector($policy->redact(...), $policy->redactUrl(...)),
        'vite' => ViteCollector::class,
    ],
    'panels' => [
        'inertia' => InertiaPanel::class,
        'vite' => VitePanel::class,
    ],
    // collector ID => application component that emits the events
    'dispatchers' => ['inertia' => 'inertia', 'vite' => 'vite'],
];
```

- A `Closure` collector entry receives the module `CapturePolicy`, so the Inertia collector redacts page props and
  URLs with the rules the Request panel applies (`sensitiveKeys`, `sensitiveKeyPrefixes`, `sensitiveKeyPatterns`). A
  bare `InertiaCollector::class` entry records the values unchanged.
- The component takes the collector on a public `eventDispatcher` property (`yii\inertia\Manager`) or on a
  constructor parameter named `eventDispatcher` (`PHPForge\Vite\Vite`). A definition is amended without
  instantiating the component; a component instantiated before the request only accepts the property form.
- A dispatcher the component already configures is kept. If the application owns a real PSR-14 dispatcher, register
  the collector as a listener on it and leave the component out of `dispatchers`.
- Use the component ID the application already has; the Vite component is often named after the entry point (for
  example `'vite' => 'inertiaVue'`).
- A `dispatchers` key naming no configured collector, an unknown component, a component that takes no dispatcher, or
  a closure definition is rejected with `InvalidConfigException`.
- Disable a provider with `'enabled' => false` on its `collectors` and `panels` entries; `dispatchers` skips a disabled
  collector. Removing the provider package while the configuration still lists its class fails with an
  `InvalidConfigException` naming the class: disable or drop the entries.

Both providers are listed under **Extensions**, like every panel registered under an ID the module does not ship.

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
application bootstrap, so they must come from the configuration; `AccessGuard`, `DispatcherAttacher`, and
`ToolbarPresenter` are resolved on the first request and may also be registered through `$module->set()` before it.

| Service                    | Responsibility                                                                          |
| -------------------------- | --------------------------------------------------------------------------------------- |
| `AccessGuard`              | Decides whether a request may reach the debugger from the IP, host, and callback rules. |
| `CapturePolicyFactory`     | Builds the redaction and body-size policy applied to every capture.                     |
| `CollectorRegistrar`       | Resolves the configured collectors into the coordinator driving the capture.            |
| `DispatcherAttacher`       | Hands each collector named in `dispatchers` to the application component it observes.   |
| `LogTargetFactory`         | Resolves the configured log target during the bootstrap.                                |
| `PanelRegistrar`           | Resolves the configured panels and their display order.                                 |
| `StandaloneActionResolver` | Merges the debugger action map and resolves routes against it.                          |
| `ToolbarPresenter`         | Renders the toolbar and writes the debug response headers.                              |

## Standalone Router

Routing details appear in Request by default. To show a separate Router panel for an existing integration, opt in
within the development-only module configuration:

```php
$config['modules']['debug']['panels']['router'] = \yii\debug\panels\RouterPanel::class;
```

---

[← Back to documentation](../README.md#documentation)
