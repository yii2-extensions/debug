# Debug repository guide

This is the Yii 22 adapter `yii2-extensions/debug`. Read `composer.json`, the current Git diff, and the relevant
`docs/` page before changing it. Version 0.2 requires PHP 8.3 or later and Yii 22; do not widen its Yii constraint
without implementing and verifying compatibility.

Application integration guidance lives in `skills/yii2-debug-inspection/SKILL.md`. Keep that the canonical skill;
client directories contain links. Public documentation is English. Put practical
recipes, including complete configuration snippets, in `docs/examples.md`, rather
than embedding duplicate usage blocks in PHPDoc.

The compiled frontend and its Vite/Vitest/Stryker toolchain are owned by `php-forge/debug-core`. This repository
has no npm build. Do not add a frontend manager to consuming applications for these packaged assets.

After changes, run the applicable checks from `docs/testing.md`: Composer validation/audit, PHPUnit, static
analysis, dependency checking, coding standards and mutation testing. Preserve existing thresholds. Validate
configuration examples against the supported Yii version. Update `CHANGELOG.md` for visible changes and
`UPGRADE.md`/`docs/migration.md` only when the actual upgrade path changes.

Do not edit generated documentation-site outputs. The framework docs site syncs this package's `docs/`, PHPDoc
and `skills/` from its manifest; coordinate changes to its shared navigation/configuration with other work.
