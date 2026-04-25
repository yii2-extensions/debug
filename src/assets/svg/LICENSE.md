# Icon licenses

The SVG glyphs shipped in this directory are sourced from third-party open-source icon packs.
Each upstream pack ships under a permissive license; the table below maps every file in this
directory to its origin and license.

| File(s)                                                                        | Origin             | License                             | Project                                                                           |
| ------------------------------------------------------------------------------ | ------------------ | ----------------------------------- | --------------------------------------------------------------------------------- |
| `ajax.svg`, `asset.svg`, `db.svg`, `profiling.svg`, `timeline.svg`, `user.svg` | Tabler Icons       | MIT                                 | https://github.com/tabler/tabler-icons                                            |
| `dump.svg`, `logs.svg`, `queue.svg`, `request.svg`                             | Phosphor Icons     | MIT                                 | https://github.com/phosphor-icons/core                                            |
| `events.svg`, `mail.svg`, `router.svg`                                         | Lucide / Heroicons | ISC / MIT                           | https://github.com/lucide-icons/lucide, https://github.com/tailwindlabs/heroicons |
| `php-alt.svg`                                                                  | Devicon            | MIT                                 | https://github.com/devicons/devicon                                               |
| `yii.svg`, `yii3_*.svg`                                                        | Yii Software LLC   | BSD-3-Clause (matches this package) | https://github.com/yiisoft/yii2                                                   |

The icons were obtained from `https://allsvgicons.com/`, which republishes the upstream files
unmodified. No icon was altered; coloring is achieved at render time via CSS `mask-image` so the
glyphs inherit the toolbar's `currentColor` and pick up the active theme.

When adding new icons, add a row above and keep the file naming convention `<concept>.svg` so the
toolbar's icon resolver finds them by concept name.
