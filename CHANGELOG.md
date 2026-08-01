# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.2.0 Under development

- refactor: Render the sidebar panel nav with the new `ui-awesome/html-core-component` `Menu` component.
- refactor: Migrate the `default` debug views to `ui-awesome/html` builder components for consistent, escape-safe rendering.
- fix: Update `yiisoft/yii2` and `yiisoft/yii2-symfonymailer` version constraints for compatibility.
- docs: Update package badges and add social media links in `README.md`.
- fix(ci): Remove unnecessary permissions and secrets from `linter.yml` workflow.
- ci: migrate reusable workflows to pinned `v2.0.1` quality and security checks, updating project status badges.
- chore: update dependency versions and improve phpinfo output handling.
- ci: exclude local agent and editor directories from quality checks.
- chore!: require the `ui-awesome` 0.8 generation (`html ^0.6`, `html-core ^0.8`, `html-core-component ^0.4`, `html-svg ^0.6`).
- feat!: redesign the debug UI with an instrument-panel identity: Yii-green `light-dark()` token system in `tokens.css`, semantic hue vocabulary for HTTP verbs, status classes, SQL tokens, and timeline categories, and a redesigned toolbar cluster. The timeline variant classes are renamed from severity (`info`/`success`/`warning`/`danger`/`muted`) to domain (`app`/`db`/`view`/`cache`/`mail`/`queue`/`other`).
- feat: add server-side SQL syntax highlighting (`SqlHighlighter`) to the db panel queries grid and the EXPLAIN view.
- feat: color timeline spans by domain category and add a chart legend; both palettes validated for color-vision accessibility.
- feat: self-host the `JetBrains Mono` and `IBM Plex Sans` webfonts through the published asset bundle and drop the Google Fonts CDN links (removes `Major Mono Display`).
- fix: prevent horizontal overflow in the history and db panel tables, keep statement-type pills and capture timestamps on one line.
- fix: keep the client's latest theme choice authoritative; the `yii_debug_theme` query snapshot no longer outranks the theme cookie on the server or the client, so stale panel links cannot revert a fresh light/dark pick.
- fix: make the toolbar follow the host application's theme; unmarked documents read as the host's light state, text-labeled theme switchers are detected, and SPA-mounted switchers are re-checked after page load.
- refactor: move the toolbar shadow DOM styles from an inline JS string into `toolbar-shadow.css`, bundled with `?inline` and sharing the token system.
- chore: keep the repository prettier configuration on two-space js/css/json indentation, overriding the `php-forge/baseline` `0.1.7` scaffold refresh (the scaffold lock now guards the file as user-modified).
- refactor!: replace the removed `DefaultsProviderInterface` classes in `yii\debug\html\defaults` with `tag()` defaults definitions.
- fix: keep the base `yii-debug-nav-link` class on the active sidebar entry now that `Menu::linkActiveClass()` replaces the link class list.
- feat: wire the semantic hue vocabulary end to end; the History method/status cells, request hero, sidebar snapshot, toolbar status badge, and AJAX popover now consume the `verb-*`/`status-*` tokens through the new `Vocabulary` helper, with tinted "ink on paper" chips replacing solid fills. Status mapping is unified to `2xx`/`3xx`/`4xx`/`5xx`: 3xx responses now read amber (previously blue or muted depending on the surface) and 4xx gain their own orange, distinct from 5xx red.
- feat: render log levels as tinted vocabulary chips (`error`/`warning`/`info`/`trace`/`profile`) backed by the new `--yii-debug-level-*` tokens, and map the db statement-type pills onto the REST verb hues (`SELECT` = get, `INSERT` = post, `UPDATE` = put, `DELETE` = delete).
- feat: add server-side micro-gauge rails behind the History Duration/Memory readouts and the Profiling duration column, scaled to the visible capture maximum (new `Gauge` and `HistoryScale` helpers, no new JavaScript).
- feat: make the timeline axis ticks adaptive; "nice" `{1, 2, 5} x 10^n` steps with second-scale labels (`1.5 s`) replace the ten fixed ticks that collided on long requests; `DataProvider::getRulers()` now treats its argument as a maximum tick count and defaults to `6`.
- fix: restore the History status chips and the log row severity tinting; the renderers emitted BEM double-dash classes (`yii-debug-badge--*`, `yii-debug-row--*`) that no stylesheet defined, so both rendered neutral.
- refactor!: remove `Panel::getSummary()` and the legacy HTML summary fallback from `Panel::getToolbarData()`; `getToolbarItems()` returning `[]` now skips the panel like `null`. Third-party panels must return structured items to surface a toolbar chip.
- refactor!: remove `DefaultController::actionToolbar()`, the light-DOM `toolbar` view, and its `Module::beforeAction()` allowlist entry; the web-component toolbar consumes the `toolbar-data` JSON endpoint only.
- refactor!: remove `DbPanel::getSummaryName()`, the eleven per-panel `summary` views, and the `ToolbarBlock`/`ToolbarLabel` HTML defaults.
- refactor!: drop `toolbar.min.css` from `DebugAsset` and delete the legacy toolbar stylesheet; the user switch view now wears the tinted `yii-debug-level-chip` vocabulary.
- refactor: delete the dead timeline hover script (its `.debug-timeline-*` selectors matched no markup); `TimelineAsset` ships CSS only.
- refactor: remove the legacy `panel.html` render branch and the `.legacy-panel` styles from the toolbar web component; panel payloads render exclusively through the structured-items path.
- refactor!: rename the `--yii-debug-panel-info` token to `--yii-debug-info` as the base informational accent; `--yii-debug-level-info` now aliases it.
- feat: restyle the generic toolbar badges (`success`/`info`/`loading`/`warning`/`danger`/`cross-request`) with the tinted "ink on paper" vocabulary formula, removing the hardcoded white-on-solid hex pairs; add the missing `.badge` base rule so the AJAX popover status cells render as pills; tokenize the neutral pill fill as `--yii-debug-panel-pill`; the profiling time/memory metrics render as neutral readouts.
- refactor!: rename the `--yii-debug-panel-success`/`--yii-debug-panel-warning`/`--yii-debug-panel-danger` tokens to `--yii-debug-success`/`--yii-debug-warning`/`--yii-debug-danger`, completing the verdict namespace started by `--yii-debug-info`; stylesheets overriding the old custom properties must adopt the new names.
- refactor: alias `--yii-debug-sql-num` to `--yii-debug-info`, factor the toolbar's duplicated viridian washes into `--accent-wash-strong`/`--accent-wash-soft` shadow-root locals, and drop the stale `#d97706` `var()` fallbacks from `main.css`.
- refactor!: narrow `Panel::getToolbarItems()` and its overrides to `: array`; `[]` replaces `null` as the "no toolbar chip" sentinel (the two were already indistinguishable in `getToolbarData()`). Subclasses overriding this protected method must change the signature from `array|null` to `array` and return `[]` instead of `null`.
- fix: drive the timeline memory chart from the design tokens — the SVG polyline stroke and gradient stops now default to `currentColor` (the memory track sets `color: var(--yii-debug-panel-primary)`), replacing the hardcoded GitHub-green ramp whose trace stayed `#1e6823` in dark mode; numeric `Svg::$gradient` values now mean per-stop `stop-opacity` (string values remain verbatim stop colors), and the gradient id is namespaced to `yii-debug-tl-memory-gradient`.
- feat: bring the Mail, Dump, and Events panels to the shared design system — Mail opens with the `.yii-debug-grid-summary` strip (message count, failed-count danger stat, working page-size selector) and its status pills and dot join the tinted vocabulary formula; Dump gains an empty-state card with a `Yii::debug()` usage snippet, the `yii-debug-grid-dump` variant, a filter-preserving `filterUrl`, a mono category column, and hue-driven type badges; Events gains an empty-state card, a summary strip (events/classes/static counts), FQCN cells split into muted namespace plus bold short name, a muted `static` badge, and a Yes/No filter dropdown, fixing its copy-pasted grid id and stale `@var` annotation.
- chore: apply the accumulated Rector backlog.
- fix(ui): raise the timeline memory chart contrast (stronger opacity ramp, `1.5` stroke) and wrap long event FQCNs mid-word at narrow viewports.
- refactor!: remove the dead timeline color API — `TimelinePanel::getColors()`/`setColors()` and `DataProvider::getColor()`/`getCssClass()`; bars are colored by domain category (`--yii-debug-cat-*` tokens) since the redesign, so the computed hex was never rendered. Configurations passing a `colors` key to the timeline panel now throw `UnknownPropertyException` and must drop it.
- fix(ui): densify the Events grid header, filter, and body cell padding under the 768px breakpoint, removing the residual horizontal scroll left by classic desktop scrollbars at narrow viewports.
- feat: highlight the SQL of `yii\db\Command::*` log entries in the Logs panel message column with the same `SqlHighlighter` token spans as the db panel queries grid.

## 0.1.1 May 18, 2026

- Fix!: Bump `yiisoft/yii2` constraint to `^2.0.56@dev || ^22.0@dev` to ensure `yii\web\ErrorHandler::EVENT_AFTER_RENDER` (introduced in `2.0.56`) is available at runtime.

## 0.1.0 May 17, 2026

- feat: initial `yii2-extensions/debug` package structure.
- Enh!: Rebuilt the debug UI: removed bundled Bootstrap4 + jQuery, scoped Pico-inspired CSS, file-based icons, standalone phpinfo, brand chip, GridViewConfig helper, deprecation shim for `data-toggle`.
- Enh!: Update README.md with new screenshots and remove deprecated extension configuration.
