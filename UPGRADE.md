# Upgrade Guide

## 0.2.0

- Upgrade PHP to 8.3 or later and migrate the application to Yii 22.0; Yii 2.0.x is not supported.
- Update this package together with `php-forge/debug` and `php-forge/debug-core`.
- Remove the old serialized captures from the debugger `dataPath`; the JSON store cannot read them. Keep unrelated
  runtime data.
- Adapt custom collectors and panels to `PHPForge\Debug\CollectorInterface` and `PHPForge\Debug\Panel`, and register
  Vite or Inertia providers explicitly; the module no longer detects them.
- Replace the removed Timeline panel with Profiling, `Module::VERSION` with `Module::getVersion()` and `QueryRow`
  properties with its getters. Remove references to `DebugSearch::$criticalCodes` and the `DbAsset`, `PhpInfoAsset`,
  `TimelineAsset` and `UserswitchAsset` classes.
- Use `data-yii-debug-toggle` instead of `data-toggle` in custom markup, and republish the debugger assets.

See the [migration guide](docs/migration.md) for details.
