# Debug extension assets

This directory contains the SCSS sources, JavaScript, SVG icons, and compiled CSS served to the debug panel
pages and the debug toolbar custom element.

## Layout

```
assets/
├── arrow.svg               # static asset
├── build.sh                # SCSS compile script
├── css/                    # compiled output (committed)
│   ├── main.css            # panel UI
│   ├── timeline.css        # timeline panel
│   └── toolbar.css         # legacy mini-toolbar (summary chips)
├── js/
│   ├── db.js               # "EXPLAIN" toggle for DB panel
│   ├── debug.js            # theme sync + tab/dropdown/collapse handlers for the panel page
│   ├── timeline.js         # timeline zoom controls
│   ├── toolbar.js          # `<yii-debug-toolbar>` custom element (shadow DOM)
│   └── userswitch.js       # "switch user" form handler
├── maximize.svg
├── scss/
│   ├── main.scss           # panel UI — Pico-inspired, scoped under `.yii-debug`
│   ├── timeline.scss
│   └── toolbar.scss        # summary-chip mini-toolbar styles
└── switch.svg
```

## Compiling

Edit the `.scss` files and run:

```sh
./build.sh
```

This invokes the Dart Sass CLI (`sass --no-source-map --style=compressed`). If no `sass` binary is found on
`PATH`, the script falls back to `npx --yes sass`. Both `sass` and `npx sass` produce identical output.

The three compiled files (`main.css`, `timeline.css`, `toolbar.css`) are committed alongside their sources
because the debug extension ships them directly — there is no CI/release step that compiles CSS for you.

## Isolation & scoping

`main.css` is **scoped** under a single `.yii-debug` class that the panel layout applies to `<body>`. Every
component uses the `yii-debug-*` prefix so no selector can leak out of the debug UI and nothing host-side
accidentally bleeds in. See `src/assets/scss/main.scss` for details and `docs/UPGRADE-2.x.md` for the
complete class-name mapping from the retired Bootstrap 4 vocabulary.

The toolbar custom element (`<yii-debug-toolbar>`) lives in an open shadow DOM and embeds its own styles
inline, so its isolation is independent of `main.css`.
