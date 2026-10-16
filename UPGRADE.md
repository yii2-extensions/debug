# Upgrade

## From 0.1 to the 0.2 development line

Upgrade PHP to 8.3 or later. Migrate Yii 2.0 applications to Yii 22 before upgrading this extension.

1. Review the [changelog](CHANGELOG.md) and the [migration guide](docs/migration.md) against the installed revision.
2. Update the debugger and the shared `php-forge/debug` and `php-forge/debug-core` dependencies together. Keep Composer's security blocking enabled, retain the application lockfile and run `composer audit`.
3. Stop development traffic and remove only the obsolete captures in the configured debugger `dataPath`. The current store uses versioned JSON snapshots and cannot read the old serialized format. New requests recreate the debugger's data. Do not delete the application's entire runtime directory.
4. Review custom collectors, panels, action routes and provider registrations as described in migration.
5. Refresh Yii's published debugger assets using the application's normal asset cleanup/deployment process, then make a request and verify the toolbar, History, Logs and any optional providers.
