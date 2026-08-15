# Upgrading

## Upgrading from 0.1 to 0.2

Version 0.2 is a development-tooling release with intentional internal API removals. Application configuration for
the debug module remains unchanged.

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
