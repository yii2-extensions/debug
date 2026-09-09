# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.2.0 Under development

- fix: improve capture reliability, sensitive-data handling, identity actions, persistence, and worker cleanup; prevent recursive debugger captures.
- feat: add Inertia and Vite panels, capture comparisons, Events inspection, unified Profiling and Timeline, and Database N+1 and EXPLAIN diagnostics.
- feat(ui): redesign navigation, toolbar, and panels with responsive layouts, accessible controls, and consistent filtering, sorting, and pagination; remove the redundant History status column.
- refactor!: move shared contracts, helpers, assets, and views to Debug Core; replace serialized storage with versioned JSON snapshots and remove superseded local APIs.
- refactor!: require Yii2 `^22.0` and ui-awesome `0.8`; adopt standalone actions and immutable factories, including `QueryRow` getters instead of public properties.
- refactor(tests): split debugger action and module tests by responsibility and centralize shared action fixtures.
- docs: focus the `README.md` on installation, essential configuration, and usage; preserve panel screenshots, clarify Yii2 `22.x` support, and remove internal implementation details and broken documentation links.

## 0.1.1 May 18, 2026

- Fix!: Bump `yiisoft/yii2` constraint to `^2.0.56@dev || ^22.0@dev` to ensure `yii\web\ErrorHandler::EVENT_AFTER_RENDER` (introduced in `2.0.56`) is available at runtime.

## 0.1.0 May 17, 2026

- feat: initial development release.
