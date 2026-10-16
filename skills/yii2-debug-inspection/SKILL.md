---
name: yii2-debug-inspection
description: Configure and inspect yii2-extensions/debug in a Yii 22 application, including development-only access, captured requests, redaction and optional Vite or Inertia providers. Use for this debugger rather than the legacy yiisoft/yii2-debug package.
license: BSD-3-Clause
metadata:
  guide: https://yii2-framework.github.io/docs/extensions/debug/
---

# Inspect a Yii 22 application with Debug

Match the application's installed `yii2-extensions/debug` revision. The 0.2 line needs PHP 8.3 and Yii 22;
do not add Yii 2.0 compatibility by changing its Composer constraint. Installing this skill does not install PHP
dependencies or enable the module.

## Consult the relevant source

In a root package checkout, use `composer show --self --format=json` for metadata. For installed dependencies,
resolve the real path and read the configured vendor directory's `composer/installed.php` (`versions[<package>]`):
`install_path`, `pretty_version` and Composer's selected `reference`. Do not blindly prefer `source.reference`
over `dist.reference`. For any live package checkout, including a symlinked path install, inspect its current
`HEAD` and files even when clean; report metadata mismatches and let the actual files govern. Uncommitted changes
have no matching SHA. Otherwise use the selected reference or a verified matching tag only after establishing
its source provenance. Compare `composer.lock`; it may differ from the inspected installation. Identify a
lock-only state when no package files are installed, and resolve dependencies separately. Use hosted pages,
catalogs or snapshots only when their source revision matches.

Prefer matching supplied Markdown and package `docs/`. Otherwise consult these published pages after checking
their revision:

- Setup and assets: <https://yii2-framework.github.io/docs/extensions/debug/installation/index.md>.
- Policies, providers and services: <https://yii2-framework.github.io/docs/extensions/debug/configuration/index.md>.
- Application recipes: <https://yii2-framework.github.io/docs/extensions/debug/examples/index.md>.
- Existing integrations: <https://yii2-framework.github.io/docs/extensions/debug/migration/index.md>.

If a page does not match, use `docs/` at the resolved reference in <https://github.com/yii2-extensions/debug>.
If unavailable or offline, inspect local source/PHPDoc and the changelog, and report the gap. For an exact API,
inspect PHPDoc or the matching type in <https://yii2-framework.github.io/docs/reference/debug.json>. Read
<https://yii2-framework.github.io/docs/llms.txt> only when the needed page is unknown.

## Configure the existing application

Inspect the entry script, Composer requirement/lock, configuration merges and any existing debugger first.
Define the development environment before loading Yii and put both bootstrap and module registration inside
`YII_ENV_DEV`. Keep production configuration valid when development dependencies are absent. Preserve the
application's own components, routes and access rules.

Retain a narrow trusted IP allowlist. A `checkAccessCallback` adds a condition after the IP check and grants
access only with `true`; it cannot bypass an IP denial. In containers, determine the IP Yii sees before changing
the list. Captures stay outside the public Web root and need writable runtime storage.

Debug Core already ships compiled assets; consumers need neither Foxy nor Node.js to publish them through Yii.
Frontend development and its Vite/Vitest/Stryker checks belong to `php-forge/debug-core`. Preserve that toolchain
when working on Core sources rather than replacing it with a native asset installer.

## Register and inspect what is needed

Use the current provider guide before adding Vite or Inertia. The module names no provider: explicitly declare
its collector and panel under the matching ID, then connect an existing component via `dispatchers`. Preserve a
component's preconfigured dispatcher. For Inertia, construct the collector with the module capture policy so
props and URLs receive redaction. Registration alone does not configure the application component or its build.

Extend default sensitive keys instead of replacing them. Request redaction does not make log messages or SQL
values safe to share. Review a capture before attaching it to a report; avoid logging secrets to test redaction.

Make an HTML application request that renders `endBody()`, open its toolbar chips and History, and confirm a
known non-sensitive log entry. JSON responses have no injected HTML toolbar. Inspect debug response headers
and History for those requests. Verify denied access as well as allowed access. Confirm provider events using
a request that actually invokes the provider component.

For upgrades from 0.1, use the migration guide: Yii 22 is required, old captures are incompatible, Timeline is
part of Profiling, provider registrations changed and controller-coupled actions use the current module APIs.
Remove only obsolete debugger data with authorization; do not clear unrelated runtime files.

Report the package/framework revisions, configuration and request checks performed, asset/storage failures,
and any checks still needing a browser or native skill activation. Never claim those checks from a static
configuration inspection alone.
