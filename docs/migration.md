# Migrate to Debug 0.2

For applications upgrading from 0.1, start with the [upgrade checklist](../UPGRADE.md). The 0.2 line targets Yii
22.0, including standalone module actions and immutable factories. It does not support Yii 2.0.x.

## Stored requests and assets

Old serialized captures are incompatible with the versioned JSON store. Stop development traffic and remove
only old debugger data at the configured `dataPath`; do not clear unrelated runtime state or rely on historical
captures remaining readable. Treat stored captures as sensitive.

Shared contracts live in `php-forge/debug`; shared storage, presentation, views and the compiled frontend live in
`php-forge/debug-core`. Yii's `DebugAsset` and `ToolbarAsset` publish those packaged assets. Remove direct
references to the old `DbAsset`, `PhpInfoAsset`, `TimelineAsset` and `UserswitchAsset` classes. Custom markup uses
`data-yii-debug-toggle`; the old `data-toggle` alias is gone. No consumer-side Node.js build is needed.

## Collectors, panels and providers

Custom collectors and panels must match the current contracts rather than importing removed internal helper
classes. A collector implements `PHPForge\Debug\CollectorInterface`; a portable panel uses
`PHPForge\Debug\Panel`. For Yii-owned panels, consult `yii\debug\Panel` at the installed revision and adapt
capture/hydration to typed snapshots. `QueryRow` exposes getters instead of its former public properties.

Optional Vite and Inertia integrations now belong to their provider packages. Register the collector and panel
explicitly under their shared stable ID, then use `dispatchers` for an existing component that accepts the
collector. The module does not autodetect provider packages. Use the capture-policy closure for Inertia to redact
props and URLs. See the [current configuration](configuration.md#provider-packages).

The standalone Timeline panel/collector was removed; use Profiling and its timeline. Router appears inside
Request by default and can be enabled as a separate panel. Mail and Queue are built-ins; `position` is rejected
for built-in panels. Extensions are ordered by configured position, then title, and include enabled panels with
no activity. Disable both a provider's collector and panel before removing its package.

## Actions and custom integrations

The module dispatches standalone actions from `Module::$actionMap`. Code coupled to the former debug controller
must use the current action/module APIs and route generation. Keep identity-action access and HTTP method
checks when extending those actions. Module services can be replaced through `components` before resolution;
see [module services](configuration.md#module-services).

`Module::VERSION` was removed; use `Module::getVersion()`. `DebugSearch::$criticalCodes` was removed: every `4xx`
and `5xx` capture is now critical, and History provides a Status column. Internal search matchers, row
normalizers and former shell DTOs are not supported extension points. Check PHPDoc at the installed revision
when migrating code that used them.

Verify application requests, stored capture loading, denied access, custom actions, provider events and asset
loading after the upgrade. Keep the original application lockfile available for rollback; old captures do not
become compatible by downgrading the code.
