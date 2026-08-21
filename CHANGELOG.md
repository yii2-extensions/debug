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
- refactor: remove two mutation-equivalent branches and close 34 simple mutation gaps across user switching, queues, Inertia, logs, routing, timeline rendering, and storage snapshots.
- refactor: remove redundant filtering and tokenization branches and close 48 simple mutation gaps across configuration, dumps, searches, profiling, routing, timelines, and grid accessibility.
- refactor: replace mutation-prone string concatenations with equivalent interpolation and formatting, removing 213 surviving mutants across debugger URLs, summaries, panels, and renderers.
- refactor: remove redundant sidebar URL guards and eliminate 50 surviving mutants across mail search and sidebar navigation.
- refactor: rework phpinfo with grouped module navigation, scoped search, compact extension summaries, accessible disclosures, and redaction of sensitive environment values from rendered output.
- feat(ui): highlight SQL in the Profiling info cell and collapse long statements behind the shared clamp, and show Inertia prop values in full behind the same clamp instead of truncating them at 100 characters.
- feat(ui): collapse the Inertia props table behind the shared clamp once it exceeds twelve rows, so a page shipping dozens of props no longer stretches the panel.
- feat(ui): extract the collapsible section into a shared `Disclosure` helper, render the Inertia raw payload through it, and give panel sections a consistent gap instead of letting a heading sit flush against the block above it.
- chore(deps): update dependencies in `composer.json`.
- refactor!: move the shared snapshot contracts and JSON persistence into `php-forge/debug-core` and remove the former Yii2 storage DTO namespace.
- refactor: consume the frontend files and shared views from `php-forge/debug-core` while keeping Yii2 asset bundle definitions, view rendering, and toolbar response injection in this adapter.
- refactor!: move framework-neutral normalization and presentation helpers to `php-forge/debug-core`.
- refactor!: move the framework-neutral panel presentation (snapshot DTOs, rows, renderers, normalizers, `Tabs`, log-level vocabulary) to `php-forge/debug-core` under `PHPForge\Debug\Panel`.
- refactor!: require Yii2 `^22.0`, dispatch endpoints as DI-injected standalone actions, and repair the user-switch panel; see `UPGRADE.md`.
- refactor!: consume the shared history view-models `PHPForge\Debug\View\History` and theme resolver `PHPForge\Debug\Theme\ThemeResolver` from debug-core; the local history widget DTOs were removed; see `UPGRADE.md` for migration details.
- refactor(panel): hydrate the User panel RBAC providers with typed `PHPForge\Debug\Panel\User\UserRbacRow` models instead of raw snapshot arrays.
- fix: prevent standalone debugger routes from persisting recursive snapshots, emitting debug headers, or rendering a nested toolbar while preserving explicit debugger logging.
- refactor: consume shared filtering, pagination, Router, and Asset Bundles UI contracts from debug-core.
- fix(ui): paginate database queries, correct event sorting, and share EXPLAIN markup.
- refactor(ui): consume shared Timeline geometry and rendering contracts.
- refactor(ui): align User guest, toolbar, and RBAC rendering and capture Guest requests for parity.
- feat(ui): align Dump, Mail, and Queue security contracts with Yii3 and redact configured queue payload keys.
- fix(ui): ship the shared keyboard-resizable drawer with Escape handling and focus restoration.
- fix: harden packaging, identity actions, persisted data, worker cleanup, storage modes, dump output, and mail capture.
- refactor: simplify log message flattening and separate mail event capture from persistence while preserving fail-open behavior.
- refactor: normalize Yii logger payloads once at the adapter boundary and remove redundant shared tuple coercion.
- perf: reuse committed snapshot manifests during mail reconciliation, append log buffers in place, and report unbiased millisecond debug durations.
- fix/docs(ui): refine filters, metrics, accessibility, history, Queue, EXPLAIN, and refresh debugger screenshots and Inertia examples.

## 0.1.1 May 18, 2026

- Fix!: Bump `yiisoft/yii2` constraint to `^2.0.56@dev || ^22.0@dev` to ensure `yii\web\ErrorHandler::EVENT_AFTER_RENDER` (introduced in `2.0.56`) is available at runtime.

## 0.1.0 May 17, 2026

- feat: initial `yii2-extensions/debug` package structure.
- Enh!: Rebuilt the debug UI: removed bundled Bootstrap4 + jQuery, scoped Pico-inspired CSS, file-based icons, standalone phpinfo, brand chip, GridViewConfig helper, deprecation shim for `data-toggle`.
- Enh!: Update README.md with new screenshots and remove deprecated extension configuration.
