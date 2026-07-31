# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.2 Under development

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
- fix: keep the client's latest theme choice authoritative — the `yii_debug_theme` query snapshot no longer outranks the theme cookie on the server or the client, so stale panel links cannot revert a fresh light/dark pick.
- fix: make the toolbar follow the host application's theme — unmarked documents read as the host's light state, text-labeled theme switchers are detected, and SPA-mounted switchers are re-checked after page load.
- refactor: move the toolbar shadow DOM styles from an inline JS string into `toolbar-shadow.css`, bundled with `?inline` and sharing the token system.
- chore: keep the repository prettier configuration on two-space js/css/json indentation, overriding the `php-forge/baseline` `0.1.7` scaffold refresh (the scaffold lock now guards the file as user-modified).
- refactor!: replace the removed `DefaultsProviderInterface` classes in `yii\debug\html\defaults` with `tag()` defaults definitions.
- fix: keep the base `yii-debug-nav-link` class on the active sidebar entry now that `Menu::linkActiveClass()` replaces the link class list.

## 0.1.1 May 18, 2026

- Fix!: Bump `yiisoft/yii2` constraint to `^2.0.56@dev || ^22.0@dev` to ensure `yii\web\ErrorHandler::EVENT_AFTER_RENDER` (introduced in `2.0.56`) is available at runtime.

## 0.1.0 May 17, 2026

- feat: initial `yii2-extensions/debug` package structure.
- Enh!: Rebuilt the debug UI: removed bundled Bootstrap4 + jQuery, scoped Pico-inspired CSS, file-based icons, standalone phpinfo, brand chip, GridViewConfig helper, deprecation shim for `data-toggle`.
- Enh!: Update README.md with new screenshots and remove deprecated extension configuration.
