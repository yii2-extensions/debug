# Upgrade

## From 0.1 to the 0.2 development line

Version 0.2 requires PHP 8.3 or later and Yii 22.0. Yii 2.0 applications cannot upgrade to this line without first
migrating the application to Yii 22. This guide describes changes already present in 0.2; documentation and skill
additions do not introduce another runtime API break.

1. Review the [changelog](CHANGELOG.md) and the [migration guide](docs/migration.md) against the installed revision.
2. Update the debugger and the shared `php-forge/debug` and `php-forge/debug-core` dependencies together. Keep
   Composer's security blocking enabled, retain the application lockfile and run `composer audit`.
3. Stop development traffic and remove only the obsolete captures in the configured debugger `dataPath`. The
   current store uses versioned JSON snapshots and cannot read the old serialized format. New requests recreate
   the debugger's data. Do not delete the application's entire runtime directory.
4. Review custom collectors, panels, action routes and provider registrations as described in migration.
5. Refresh Yii's published debugger assets using the application's normal asset cleanup/deployment process, then
   make a request and verify the toolbar, History, Logs and any optional providers.

The basic `bootstrap`/`modules` registration and development-only access guard remain applicable. Use
[installation](docs/installation.md) for a fresh application and [examples](docs/examples.md) for current recipes.
