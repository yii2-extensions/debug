# Testing

This package provides a consistent set of [Composer](https://getcomposer.org/) scripts for local validation.

Tool references:

- [Composer Require Checker](https://github.com/maglnet/ComposerRequireChecker) for dependency definition checks.
- [Easy Coding Standard (ECS)](https://github.com/easy-coding-standard/easy-coding-standard) for coding standards.
- [Infection](https://infection.github.io/) for mutation testing.
- [PHPStan](https://phpstan.org/) for static analysis.
- [PHPUnit](https://phpunit.de/) for unit tests.
- [Rector](https://github.com/rectorphp/rector) for automated refactoring.

## Install and audit

Use a source checkout with PHP 8.3 or later and the extensions listed in `composer.json` (tests also use `gd`
and `pdo_sqlite`). Composer installs the development tools and the supported Yii 22 dependency:

```bash
composer install
composer validate --strict
composer audit
```

Keep security blocking and auditing enabled; do not bypass advisories to obtain a successful test run. The
library's local `composer.lock` is ignored, while a consuming application should retain its own lockfile.
A fresh resolution may differ from an old local lock; report the versions used. Yii 2.0.x is outside this line's
supported matrix.

## PHP checks

```bash
composer tests
composer static
composer check-dependencies
vendor/bin/ecs check
```

`composer ecs` applies coding-style fixes; use the non-mutating command above for verification. Run Rector only
when intentionally refactoring, and inspect its diff. `composer rector` changes source files.

## Mutation and coverage

With PCOV or Xdebug coverage enabled:

```bash
composer mutation
composer mutation-static
composer tests -- --coverage-html code_coverage
```

The mutation scripts keep MSI and covered MSI at 100. `mutation-static` also checks mutants with PHPStan.
Use local reports for local runs; dashboard uploads require their configured credentials. A skipped mutation
run or a failed initial suite is not a passing score.

## Documentation and configuration examples

Copy the complete `config/debug.php` and `config/debug-vite.php` snippets from the
[usage examples](examples.md) into temporary PHP files and lint each with
`php -l`. Load each configuration in a development Yii 22 Web application, merge
it with the application's existing configuration, bootstrap it and verify module
access. In a separate production process, the example must return an empty array.
The Vite example additionally needs the actual provider package and an existing
compatible component; inspect an emitted event rather than panel presence only.

For browser validation, make an HTML request, inspect its toolbar/History and log capture, and verify rejected
access from an unlisted IP. Static example checks do not prove browser rendering or native agent activation.
Check changed Markdown links and the repository's EditorConfig/Markdown lint rules.

## Frontend ownership

This Yii adapter has no `package.json` or local JavaScript tests. Its assets come prebuilt from
`php-forge/debug-core`. In a Core source checkout, keep Node.js and run its locked pipeline:

```bash
npm ci
npm run test:js
npm run test:mutation
npm run build
npm run check:size
npm run check:contrast:strict
```

Core's CI also checks formatting, linting, browser discovery and the large-data browser harness. A Vite update
can change generated output: inspect and commit the regenerated Core `resources/assets/dist/` files together
with its dependency lock update. Keep the CI drift check enabled. Diagnose the failing job before applying
another package's compatibility patch; passing tests/mutation do not imply an unchanged build output.

---

[Back to documentation](../README.md#documentation)
