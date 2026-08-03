# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.2.0 Under development

- refactor: render the sidebar panel nav with the new `Menu` component.
- refactor: migrate the default debug views to escape-safe HTML builders.
- fix: update the Yii and Symfony Mailer compatibility constraints.
- docs: update package badges and social links.
- fix(ci): remove unnecessary permissions and secrets from the linter workflow.
- ci: pin the reusable quality and security workflows to v2.0.1.
- chore: update frontend dependencies and reusable GitHub Actions workflows.
- chore: update dependency versions and improve phpinfo output handling.
- ci: exclude local agent and editor directories from quality checks.
- feat!: migrate to the ui-awesome 0.8 generation and redesign the debug UI.
- feat(ui): wire semantic vocabulary end to end, add micro-gauges, and make timeline ticks adaptive.
- refactor!: purge the legacy toolbar and `getSummary()` pipeline and restyle toolbar badges.
- feat(ui)!: polish the Mail, Dump, and Events panels and tokenize the timeline memory chart.
- chore: apply the accumulated Rector backlog.
- fix(ui): improve timeline contrast and wrap long event class names on narrow viewports.
- refactor!: remove the dead timeline color API and improve the Events grid on narrow viewports.
- feat(ui): fix Router details, grid overflow, category labels, and panel empty states.
- feat: add the Inertia panel, expose Vite assets, improve log messages, and polish panel navigation.
- refactor!: simplify debug storage, normalization, assets, and accessible UI for `0.2`; see `UPGRADE.md` for migration details.
- refactor!: replace serialized panel data with atomic versioned JSON, strict snapshot DTO hydration, and isolated JSON-safe panel failures.
- refactor: remove mutation-equivalent branches, isolate test sessions, and strengthen `GridViewConfig`, `FQCN`, and `Avatar` coverage.
- refactor: remove three mutation-equivalent conversions and close 36 simple mutation gaps across snapshots, renderers, queues, and log summaries.

## 0.1.1 May 18, 2026

- Fix!: Bump `yiisoft/yii2` constraint to `^2.0.56@dev || ^22.0@dev` to ensure `yii\web\ErrorHandler::EVENT_AFTER_RENDER` (introduced in `2.0.56`) is available at runtime.

## 0.1.0 May 17, 2026

- feat: initial `yii2-extensions/debug` package structure.
- Enh!: Rebuilt the debug UI: removed bundled Bootstrap4 + jQuery, scoped Pico-inspired CSS, file-based icons, standalone phpinfo, brand chip, GridViewConfig helper, deprecation shim for `data-toggle`.
- Enh!: Update README.md with new screenshots and remove deprecated extension configuration.
