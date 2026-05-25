# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.2 Under development

- refactor: Render the sidebar panel nav with the new `ui-awesome/html-core-component` `Menu` component.

## 0.1.1 May 18, 2026

- Fix!: Bump `yiisoft/yii2` constraint to `^2.0.56@dev || ^22.0@dev` to ensure `yii\web\ErrorHandler::EVENT_AFTER_RENDER` (introduced in `2.0.56`) is available at runtime.

## 0.1.0 May 17, 2026

- feat: initial `yii2-extensions/debug` package structure.
- Enh!: Rebuilt the debug UI: removed bundled Bootstrap4 + jQuery, scoped Pico-inspired CSS, file-based icons, standalone phpinfo, brand chip, GridViewConfig helper, deprecation shim for `data-toggle`.
- Enh!: Update README.md with new screenshots and remove deprecated extension configuration.
