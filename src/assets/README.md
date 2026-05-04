# yii2-extensions/debug — client assets

Source layout for the debug toolbar and panel UI. The committed `dist/` directory
is the runtime artifact referenced by the PHP `AssetBundle` classes; rebuild it
whenever anything under `src/` changes.

## Layout

```text
src/
├── core/        Cross-cutting modules registered by DebugAsset.
│   ├── debug.js            Theme sync + tab/dropdown/collapse handlers.
│   ├── theme-toggle.js     Brand-bar theme chip (light/dark + iframe sync).
│   └── history-cursor.js   Index-page row-click navigation + cursor browser.
├── panels/      Panel-specific behavior.
│   ├── db.js               "EXPLAIN" toggle for DB panel.
│   ├── timeline.js         Timeline zoom controls.
│   ├── userswitch.js       "Switch user" form handler.
│   └── phpinfo-search.js   Module filter + IntersectionObserver TOC sync.
├── toolbar/     Floating toolbar Web Component.
│   ├── index.js            Entry — registers <yii-debug-toolbar>.
│   ├── element.js          HTMLElement + shadow DOM.
│   ├── messaging.js        postMessage + iframe theme sync.
│   └── state.js            Expand/collapse + drag/resize state.
└── styles/      Native CSS sources (no SCSS).
    ├── main.css            Panel UI, scoped under .yii-debug.
    ├── toolbar.css         Mini-toolbar summary chips.
    ├── timeline.css        Timeline panel.
    └── *.entry.js          One-line shims that import each .css file.
dist/            Build output. Committed. Filenames are fixed.
├── js/                     debug.js, theme-toggle.js, history-cursor.js,
│                           db.js, timeline.js, userswitch.js,
│                           phpinfo-search.js, toolbar.js
└── css/                    main.min.css, toolbar.min.css, timeline.min.css
svg/             Static SVG icons. Untouched by the build.
```

## Build

Requires Node `>=20.10` (LTS pinned via `.nvmrc`).

```sh
npm install
npm run build
```

For continuous rebuilds during development:

```sh
npm run dev
```

## Output contract

The Vite config emits fixed filenames so the `AssetBundle` classes (`DebugAsset`,
`TimelineAsset`, `DbAsset`, `UserswitchAsset`, `PhpInfoAsset`) can reference
`dist/js/*.js` and `dist/css/*.min.css` directly. Yii's `AssetManager` adds
cache-busting at publish time, so do not introduce content hashes here.

## Isolation & scoping

`main.css` is **scoped** under a single `.yii-debug` class that the panel layout
applies to `<body>`. Every component uses the `yii-debug-*` prefix so nothing
leaks out of the debug UI and nothing host-side bleeds in. The toolbar custom
element (`<yii-debug-toolbar>`) lives in an open shadow DOM and embeds its own
styles, so its isolation is independent of `main.css`.
