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

## Inertia and Vite

Both integrations are owned by their provider packages: `php-forge/vite` ships
`PHPForge\Vite\Debug\{ViteCollector, VitePanel}` and `php-forge/inertia` ships
`PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel}`. The debugger contains no Vite or Inertia code; it adapts
those portable objects like any other external panel and lists them under **Extensions**.

Each provider service emits a single event type, so its collector is also its own PSR-14 dispatcher and the
application writes no dispatcher code:

```php
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};
use PHPForge\Inertia\Protocol;
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

$viteCollector = new ViteCollector();
$inertiaCollector = new InertiaCollector();

$config['components']['vite']['__construct()']['eventDispatcher'] = $viteCollector;
$config['components']['inertia']['protocol'] = Protocol::create(eventDispatcher: $inertiaCollector);
$config['modules']['debug']['collectors'] = ['vite' => $viteCollector, 'inertia' => $inertiaCollector];
$config['modules']['debug']['panels'] = [
    'vite' => new VitePanel(),
    'inertia' => new InertiaPanel(),
];
```

Use the component IDs the application already configures, and assign each key rather than replacing the whole
`components` or `modules` array. Pass `$policy->redact(...)` and `$policy->redactUrl(...)` to `InertiaCollector` to
apply the module's redaction rules; the default keeps the captured values unchanged.

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

## Standalone Router

Routing details appear in Request by default. To show a separate Router panel for an existing integration, opt in
within the development-only module configuration:

```php
$config['modules']['debug']['panels']['router'] = \yii\debug\panels\RouterPanel::class;
```

---

[← Back to documentation](../README.md#documentation)
