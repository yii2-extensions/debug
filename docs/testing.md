# Testing

This package provides a consistent set of [Composer](https://getcomposer.org/) scripts for local validation.

Tool references:

- [Actionlint](https://github.com/rhysd/actionlint) for GitHub Actions workflow linting.
- [Codespell](https://github.com/codespell-project/codespell) for spelling checks.
- [Composer Require Checker](https://github.com/maglnet/ComposerRequireChecker) for dependency definition checks.
- [Easy Coding Standard (ECS)](https://github.com/easy-coding-standard/easy-coding-standard) for coding standards.
- [EditorConfig Checker](https://github.com/editorconfig-checker/editorconfig-checker) for EditorConfig compliance.
- [Gitleaks](https://github.com/gitleaks/gitleaks) for secret scanning.
- [Infection](https://infection.github.io/) for mutation testing.
- [Markdownlint-cli2](https://github.com/DavidAnson/markdownlint-cli2) for Markdown linting.
- [PHPStan](https://phpstan.org/) for static analysis.
- [PHPUnit](https://phpunit.de/) for unit tests.
- [Prettier](https://prettier.io/) for Markdown and YAML formatting checks.
- [Rector](https://github.com/rectorphp/rector) for automated refactoring.
- [Yamllint](https://github.com/adrienverge/yamllint) for YAML linting.
- [Zizmor](https://github.com/zizmorcore/zizmor) for GitHub Actions security audits.

## Automated refactoring (Rector)

Run Rector to apply automated code refactoring. This command modifies files.

```bash
composer rector
```

Preview the refactoring without modifying files.

```bash
composer rector-check
```

## Coding standards (ECS)

Check coding standards without modifying files.

```bash
composer ecs-check
```

Apply coding standard fixes. Review the changes before keeping them.

```bash
composer ecs
```

## Dependency definition check

Verify that runtime dependencies are correctly declared in `composer.json`.

```bash
composer check-dependencies
```

## Mutation testing (Infection)

Run mutation testing with PCOV or Xdebug coverage enabled.

```bash
composer mutation
```

Run mutation testing with PHPStan static analysis enabled.

```bash
composer mutation-static
```

Thresholds, thread count and memory options live in `infection.json5`. CI enforces the same thresholds: MSI and covered
MSI stay at 100. Use local reports for local runs; dashboard uploads require their configured credentials. A skipped
mutation run or a failed initial suite is not a passing score.

## Repository quality checks

Make the CLI tools used by the shared quality workflow available on `PATH`: actionlint with its ShellCheck helper,
codespell, EditorConfig Checker, markdownlint-cli2, Prettier and yamllint. Run all checks:

```bash
composer quality
```

Each tool also has its own script:

```bash
composer actionlint
composer codespell
composer editorconfig-checker
composer markdownlint
composer prettier
composer yamllint
```

The linter configuration lives in `.github/linters`, and the Prettier configuration in `.prettierrc.json` and
`.prettierignore`. CI uses the same files.

## Static analysis (PHPStan)

Run static analysis.

```bash
composer static
```

## Unit tests (PHPUnit)

Run the full test suite.

```bash
composer tests
```

## Workflow security checks

Make zizmor and Gitleaks available on `PATH`. Audit the current workflow files with the same pedantic persona used by
the shared security workflow:

```bash
composer zizmor
```

Scan Git history for secrets with the repository's Gitleaks configuration:

```bash
composer gitleaks
```

Run both checks. Findings make the command fail; inspect them rather than disabling the checks.

```bash
composer security
```

Zizmor needs GitHub API access for its online audits. If the tool runs offline, report that limit separately. To request
an offline audit explicitly:

```bash
composer zizmor -- --offline
```

Report hosted CI execution and its PHP/OS matrix separately from these local commands. Keep generated reports in ignored
`internal/`.

## Passing extra arguments

Composer scripts support forwarding additional arguments using `--`.

Run PHPUnit with code coverage report generation.

```bash
composer tests -- --coverage-html internal/coverage
```

Run PHPStan with a different memory limit.

```bash
composer static -- --memory-limit=512M
```

## Install and audit

Use a source checkout with PHP 8.3 or later and the extensions listed in `composer.json` (tests also use `gd` and
`pdo_sqlite`). Composer installs the development tools and the supported Yii 22 dependency:

```bash
composer install
composer validate --strict
composer audit
```

Keep security blocking and auditing enabled; do not bypass advisories to obtain a successful test run. The library's
local `composer.lock` is ignored, while a consuming application should retain its own lockfile. A fresh resolution may
differ from an old local lock; report the versions used. Yii 2.0.x is outside this line's supported matrix.

## Documentation and configuration examples

Copy the complete `config/debug.php` and `config/debug-vite.php` snippets from the [usage examples](examples.md) into
temporary PHP files and lint each with `php -l`. Load each configuration in a development Yii 22 Web application, merge
it with the application's existing configuration, bootstrap it and verify module access. In a separate production
process, the example must return an empty array. The Vite example additionally needs the actual provider package and an
existing compatible component; inspect an emitted event rather than panel presence only.

For browser validation, make an HTML request, inspect its toolbar/History and log capture, and verify rejected access
from an unlisted IP. Static example checks do not prove browser rendering or native agent activation. Check changed
Markdown links and the repository's EditorConfig/Markdown lint rules.

## Frontend ownership

This Yii adapter has no `package.json` or local JavaScript tests. Its assets come prebuilt from `php-forge/debug-core`.
In a Core source checkout, keep Node.js and run its locked pipeline:

```bash
npm ci
npm run test:js
npm run test:mutation
npm run build
npm run check:size
npm run check:contrast:strict
```

Core's CI also checks formatting, linting, browser discovery and the large-data browser harness. A Vite update can
change generated output: inspect and commit the regenerated Core `resources/assets/dist/` files together with its
dependency lock update. Keep the CI drift check enabled. Diagnose the failing job before applying another package's
compatibility patch; passing tests/mutation do not imply an unchanged build output.

---

[Back to documentation](../README.md#documentation)
