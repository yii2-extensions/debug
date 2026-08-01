# Upgrading

## Upgrading from 0.1 to 0.2

Version 0.2 is a development-tooling release with intentional internal API removals. Application configuration for
the debug module remains unchanged.

### Clear stored debug snapshots

The on-disk snapshot and manifest format is now versioned and stores each request in a single serialized envelope.
Snapshots written by 0.1 are intentionally incompatible. Delete the configured `Module::$dataPath` contents during
the upgrade; new requests will recreate the manifest and snapshots automatically.

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

Typed panel DTOs now expose `fromMixed()` factories and aggregate helpers directly. Prefer public panel and module
contracts instead of importing classes from these internal namespaces.

### Resolve the package version through Composer

`Module::VERSION` was removed. Use `Module::getVersion()` when displaying the active version; it now resolves the
installed package metadata through Composer and reports `unknown` when metadata is unavailable.
