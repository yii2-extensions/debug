# Upgrading

## Upgrading from 0.1 to 0.2

Version 0.2 is a development-tooling release with intentional internal API removals. Application configuration for
the debug module remains unchanged.

### Yii2 22 standalone actions replace the debugger controllers

The package now requires `yiisoft/yii2` `^22.0` and dispatches every debugger endpoint as a standalone action through
`Module::$actionMap` (the inline action lifecycle introduced in Yii2 22). `yii\debug\controllers\DefaultController`
and `yii\debug\controllers\UserController` were removed; the endpoints live under `yii\debug\actions\*`:

- Built-in: `index`, `view`, `php-info`, `toolbar-data`, `download-mail`, `set-identity`, `reset-identity`.
- Panel-registered: `db-explain` (`DbPanel`), `queue-job` (`QueuePanel`).

Consequences:

- Routes drop the controller segment: `debug/default/view` becomes `debug/view` and `debug/user/set-identity`
  becomes `debug/set-identity`. Update bookmarks or custom links pointing at the old URLs.
- Custom panels contribute endpoints through `Panel::$actions`, keyed by action ID and merged into the module's
  `actionMap` (entries configured directly on `actionMap` win); plain class-string entries suffice. Custom actions
  extend `yii\debug\actions\Action`, which provides snapshot loading, shell preparation, and layout rendering
  without a controller.
- `UserPanel::$ruleUserSwitch` and the user-switch access filter now scope by action IDs
  (`'actions' => ['set-identity', 'reset-identity']`). Custom rules that matched the removed `user` controller via a
  `controllers` constraint must switch to the `actions` constraint.
- `set-identity` and `reset-identity` accept only POST requests with the application's valid CSRF token. Replace any
  direct GET or tokenless integrations; the bundled User panel forms already satisfy both requirements.
- Debugger views and widgets build links with `Module::route('<action>', [...])`, which returns a module-absolute
  route that works without an active controller context.

### Panel actions receive their panel through `run()` parameter injection

`ExplainAction::$panel` and `JobAction::$panel` were removed. The module now registers every enabled panel in its
service locator under the panel's class name and each ancestor class below `yii\debug\Panel`, and the
standalone-action binder injects the instance as a typed `run()` parameter:

- `ExplainAction::run(string $seq, string $tag, DbPanel $panel)`
- `JobAction::run(string $seq, string $tag, QueuePanel $panel)`
- `SetIdentityAction::run(User $user, Request $request)` and `ResetIdentityAction::run(User $user)` resolve the
  application components by parameter name.

Custom panels register plain class names in `Panel::$actions` — no `'panel' => $this` config array is needed.
Type-hint the panel class on `run()`; a configured panel subclass satisfies a built-in type hint. When the required
panel is disabled or unregistered, dispatch fails with `yii\web\ServerErrorHttpException`
("Could not load required service") instead of the former guarded HTTP 500.

The binder binds request parameters by name before falling back to injection, so `panel`, `user`, and `request` act
as reserved query-parameter names on these routes; a crafted value for one of them surfaces as an HTTP 500.

### Clear stored debug snapshots

The on-disk snapshot and manifest format is now versioned JSON. Each request is written atomically to `<tag>.json`,
and request summaries are indexed in `index.json`. Storage is at version `4`; older snapshots aren't migrated or
deleted automatically. Clear the debug storage directory once during the upgrade, then browse the site to repopulate
the history.

Panel persistence is now an explicit DTO contract. `Panel::$data`, `Panel::save()`, and `Panel::load()` were removed.
Panels capture a `PanelSnapshot` through `capture()` and hydrate validated JSON through `hydrate()`. The legacy
`FlattenException` serialization wrapper was replaced by the non-executable `ExceptionSnapshot` DTO.

The storage contracts now live in `php-forge/debug-core` under `PHPForge\Debug\Storage`. The former
`yii\debug\storage` DTO names were removed; import the core namespace directly.
`yii\debug\storage\SnapshotStore` remains a Yii2 facade so filesystem failures continue to surface as
`yii\base\InvalidConfigException`.

New debug storage directories and files now default to owner-only modes `0700` and `0600`. Existing directories are
left unchanged to avoid silently altering deployment permissions; review and migrate them explicitly when appropriate.
Applications that intentionally share debug data with a development group can retain the former behavior by setting
`Module::$dirMode` to `0775` and `Module::$fileMode` to `0664`. Newly captured `.eml` files follow the same module
modes and mail persistence failures remain fail-open after the application mailer has completed.

The phpinfo presentation classes also moved to `php-forge/debug-core`: import `PHPForge\Debug\PhpInfo\*` instead of
the removed `yii\debug\widgets\phpinfo\*` namespace. `PhpInfoDataNormalizer::capture()` now buffers the live
`phpinfo()` output that the view previously captured inline.

### Configure capture through collectors

Built-in data acquisition moved from panels to native collectors under `yii\debug\collectors\*`, registered by default
and paired with their panels by stable ID (`asset`, `config`, `db`, `dump`, `event`, `inertia`, `log`, `mail`,
`profiling`, `queue`, `request`, `router`, `timeline`, `user`). Panels are pure presentation now; collectors own the
per-request lifecycle: they subscribe to framework events at `Application::EVENT_BEFORE_REQUEST` and detach again
after the snapshot is exported, so long-running workers start every request clean.

Capture-side panel options move to the matching collector entry under the new `collectors` module setting:

- `panels.request.censoredVariableNames` / `censorString` / `displayVars` → `collectors.request.*`.
- `panels.router.categories` (and `setCategories()`) → `collectors.router`.
- `panels.dump.categories` / `depth` / `highlight` / `varDumpCallback` → `collectors.dump.*`; the callback now
  receives the `DumpCollector` instead of the panel. Callback output is treated as untrusted text and HTML-escaped;
  use the built-in highlighter rather than returning custom markup.
- `panels.mail.mailPath` → `collectors.mail.mailPath`.
- `panels.db.dbEventNames` / `excessiveCallerThreshold` / `ignoredPathsInBacktrace` → `collectors.db.*`; the panel
  keeps `db` (EXPLAIN connection), `criticalQueryThreshold`, `defaultFilter`, and `defaultOrder`.
- `panels.user.userComponent` also exists on `collectors.user`; the panel keeps its own copy for the switch UI.

`Panel::capture()` remains only as the extension point for custom panels that register no collector.
`Panel::getLogMessages()` and `Panel::getLogTarget()` were removed — extend `yii\debug\collectors\Collector`, which
provides both, for custom capture logic.

### Import the shared panel presentation from the core

The framework-neutral presentation layer moved to `php-forge/debug-core`; import `PHPForge\Debug\Panel\<Domain>\*`
instead of the removed `yii\debug\panels\<domain>\*` namespaces (`Asset`, `Config`, `Db`, `Dump`, `Event`, `Inertia`,
`Log`, `Mail`, `Profile`, `Queue`, `Request`, `Router`, `Timeline`, `User`), plus `PHPForge\Debug\Panel\MemorySample`.
Only the adapter-coupled `RouterRenderer` and `TimelineRenderer` remain under `yii\debug\panels\*`.

Related moves and signature changes:

- `yii\debug\widgets\Tabs` → `PHPForge\Debug\Helper\Tabs`.
- `yii\debug\helpers\Vocabulary::logLevel()` → `PHPForge\Debug\Helper\Vocabulary::logLevel()`, with the level wire
  values published as `PHPForge\Debug\Helper\LogLevel` constants.
- `DbPanel::canBeExplained()` → `PHPForge\Debug\Panel\Db\DbQueryRenderer::canBeExplained()`;
  `DbPanel::typeBadgeVariant()` was removed — call `Vocabulary::sqlVerb()` directly.
- `LogCellRenderer::renderMessageCell()`, `DumpCardRenderer::renderMessageCell()`, and
  `DbQueryRenderer::renderQueryCell()` receive a `Closure(array): string` trace-line renderer (pass
  `$panel->getTraceLine(...)`) instead of the panel instance.

### Read typed rows in custom columns and callbacks

Every panel whose rows have a known shape now narrows them **once, at capture time**, and persists them typed. The
data providers therefore carry row objects instead of associative arrays, so GridView `value` / `rowOptions`
callbacks and custom columns receive an object:

```php
// Before
'value' => static fn(mixed $data): string => LogRow::fromMixed($data)->category,

// After
'value' => static fn(LogRow $data): string => $data->category,
```

The `fromMixed()` factories were removed from `LogRow`, `DumpRow`, `EventRow`, `ProfileRow`, `QueryRow`, `JobRecord`,
`MailMessage`, and `HistoryRow`; `HistoryRow::fromSummary()` replaces them for the history grid. `LogCounts` and
`QueueSummary` take typed rows through `fromRows()` / `fromRecords()` instead of `fromPanelData()`.

Two grid attributes were renamed to match their typed property, which changes the query string:

- the Log grid sorts on `timeSincePrevious` (was `time_since_previous`), so `?sort=time_since_previous` becomes
  `?sort=timeSincePrevious`;
- the Mail grid filters on `replyTo` (was `reply`), so `?MailSearch[reply]=` becomes `?MailSearch[replyTo]=`. This
  also fixes the Reply-To filter, which silently matched nothing.

Panels contributing to the timeline memory chart now implement `ProvidesMemorySamples` and return
`list<MemorySample>`. `Svg::$listenMessages` accepts any panel implementing it again — 0.2 development builds briefly
recognised only the Log and Profiling panels.

### Drop removed extension points

- `NavigationButton` and `Panel::hasRequestNavigation()`: the Prev/Next/All navigation moved into the sidebar, which
  reads `SidebarSnapshot`; the hook was inert.
- `QueueSummary::recordsForComponent()`, `QueueCardRenderer::renderSummaryHeader()`, and
  `DbPanel::isNumberOfCallsExcessive()`: unused. Use `QueueSummary::componentIds()` and
  `DbPanel::getExcessiveCallers()`.
- `DbPanel::calculateTimings()` now reads the live profile log only. Use `DbPanel::getRows()` for the captured
  statements of a loaded snapshot.

### Use the consolidated asset bundle

`DebugAsset` now publishes one `debug.min.css` stylesheet and one `debug.min.js` script for all panel pages. Remove
direct references to the deleted `DbAsset`, `PhpInfoAsset`, `TimelineAsset`, and `UserswitchAsset` classes.

Custom panel markup must use `data-yii-debug-toggle`. The deprecated Bootstrap-compatible `data-toggle` alias has
been removed.

### Update extensions that used internal helpers

Framework-neutral helpers now live under `PHPForge\Debug\Helper`. Replace imports of `yii\debug\helpers\Avatar`,
`CellMore`, `Coerce`, `Disclosure`, `EmptyState`, `Format`, `Fqcn`, `Gauge`, and `Icon` with their core namespace
equivalents. The neutral `Vocabulary::verb()`, `Vocabulary::sqlVerb()`, and `Vocabulary::statusClass()` methods moved
to `PHPForge\Debug\Helper\Vocabulary`. No compatibility aliases are provided.

`yii\debug\helpers\Vocabulary` remains available only for the Yii2-specific `logLevel()` mapping backed by
`yii\log\Logger`.

The following implementation helpers were removed and are no longer extension points:

- `components/search/Filter` and the `components/search/matchers` classes;
- `helpers/RowField`;
- the panel `*Normalizer` classes for database, dump, event, log, mail, profiling, and queue rows;
- `widgets/shell/ShellDataNormalizer`;
- `ConfigPanel::getPhpInfo()`.

Typed panel DTOs now expose their capture and hydration factories directly. Prefer public panel and module contracts
instead of importing classes from these internal namespaces.

### Resolve the package version through Composer

`Module::VERSION` was removed. Use `Module::getVersion()` when displaying the active version; it now resolves the
installed package metadata through Composer and reports `unknown` when metadata is unavailable.
