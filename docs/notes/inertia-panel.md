# Future work — InertiaPanel for `yii2-extensions/inertia`

**Status:** not started — captured here as a follow-up so we don't lose the design.

## Where it lives

NOT in `yii2-extensions/debug`. Ships inside `yii2-extensions/inertia` so the Yii debug
module stays frontend-agnostic. Auto-registration follows the same pattern that
`yii2-queue` uses to replace the `QueuePanel` placeholder:

```php
// vendor/yii2-extensions/inertia/src/Bootstrap.php
public function bootstrap($app)
{
    if (isset($app->modules['debug'])) {
        $app->modules['debug']->panels['inertia'] = ['class' => \yii\inertia\debug\InertiaPanel::class];
    }
}
```

If the debug module isn't installed, nothing happens. If it is, the panel appears.

## What it captures

Hook into `Yii::$app->inertia` (or `Manager`) at panel `init()` and listen for the response
event so we capture what was actually serialized to the client.

Per request, save:

- **Component name** — e.g. `Site/Index`, `Auth/Login/Form`.
- **Props** — the merged `props` array passed to the component, **with redaction** of
  keys matching `password|token|secret|apiKey|authorization|cookie` (case-insensitive).
- **Shared props** — the `shared` callbacks from the Inertia config, evaluated and shown
  alongside the per-request props (`auth`, `flash`, `errors`, ...).
- **Asset version** — `Inertia::version` value sent in the response.
- **Response kind** — `full | partial | lazy` (derived from `X-Inertia` request header
  presence and `X-Inertia-Partial-Component` / `X-Inertia-Partial-Data`).
- **Root view** — path of the root view template that wrapped the response.
- **URL** — the URL Inertia echoed back (handy for debugging redirects/version mismatches).

## Toolbar chip

- Always show when the panel is registered.
- Value: component name (truncated).
- Status variant:
  - `info` for full responses.
  - `success` for partial reloads.
  - `warning` if `X-Inertia-Version` mismatch was detected.

Icon: `inertia` (would need an SVG; can reuse a generic component icon if absent).

## Detail view

```
┌────────────────────────────────────────────┐
│ Component:    Site/Index                   │
│ Type:         full | partial | lazy        │
│ Asset version: 1777136585                  │
│ Root view:    @app/resources/views/app.php │
├────────────────────────────────────────────┤
│ Props (this request)                       │
│   auth.user.id      = 1                    │
│   auth.user.email   = "***" (redacted)     │
│   ...                                      │
├────────────────────────────────────────────┤
│ Shared props                               │
│   appName = "My Application"               │
│   ...                                      │
├────────────────────────────────────────────┤
│ Inertia headers                            │
│   X-Inertia: true                          │
│   X-Inertia-Version: 1777136585            │
│   ...                                      │
└────────────────────────────────────────────┘
```

## Open questions

- **Prop serialization size** — large props (collections, big arrays) bloat the saved
  `.data` file. Decide on a max size per-prop with a "truncated, X bytes" indicator.
- **Lazy props** — should we eagerly evaluate them for display? Probably no — that
  defeats the point of lazy. Show their key names + `<lazy>` placeholder.
- **Privacy** — auto-redaction is the easy default. Allow opt-in via panel config:
  ```php
  'panels' => [
      'inertia' => [
          'class' => InertiaPanel::class,
          'redactKeys' => ['password', 'token', 'mySecretField'],
      ],
  ],
  ```

## VitePanel (sibling, lower priority)

Same architectural pattern, ships with whatever package wraps Vite for Yii. Captures:

- Mode: `dev server` vs `build`.
- Manifest entries (read `@webroot/build/.vite/manifest.json` if it exists).
- Chunk sizes, dependency graph.
- HMR state in dev.

Less urgent than InertiaPanel — DevTools' Network panel covers most dev needs. The
real value is inspecting the chunk graph in production builds without spinning up
`vite preview`.
