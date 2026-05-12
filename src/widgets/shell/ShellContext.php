<?php

declare(strict_types=1);

namespace yii\debug\widgets\shell;

use yii\debug\Panel;

/**
 * Typed view-model for the debugger shell (brand bar + sidebar + main wrapper).
 *
 * The `$mode` discriminator picks the shell layout: 'view'` (panel detail), 'index'` (history grid) or 'bare' (no shell
 * phpinfo / db-explain). Every loose-array access on the `$shellData` payload that the layout would do inline lives
 * here once: panels map, manifest, summary, theme icons, version strings, Configuration chip URL.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class ShellContext
{
    public const string MODE_BARE = 'bare';
    public const string MODE_INDEX = 'index';
    public const string MODE_VIEW = 'view';

    public function __construct(
        /**
         * Shell mode ('view' / 'index' / 'bare').
         */
        public string $mode,
        /**
         * `true` when the shell layout (brand bar + sidebar) should render; `false` for 'bare' which echoes raw content
         * into `<body>`.
         */
        public bool $useShell,
        /**
         * Document `<title>` already escaped.
         */
        public string $title,
        /**
         * Theme attribute map applied to the `<html>` tag (`['data-yii-debug-theme' => 'dark'|'light']`); empty array
         * when the theme is neither dark nor light (defaults to system preference via CSS).
         *
         * @var array<string, string>
         */
        public array $debugThemeAttributes,
        /**
         * Resolved theme ('dark' / 'light') fed to the shell header for the toggle button.
         */
        public string $resolvedTheme,
        /**
         * Pre-rendered sun glyph for the theme-toggle button.
         */
        public string $themeIconSun,
        /**
         * Pre-rendered moon glyph for the theme-toggle button.
         */
        public string $themeIconMoon,
        /**
         * Yii framework version label shown in the brand bar.
         */
        public string $yiiVersion,
        /**
         * PHP version label shown in the brand bar.
         */
        public string $phpVersion,
        /**
         * Formatted peak memory ('X.XX MB'); `null` when no captured summary is present.
         */
        public string|null $peakMemory,
        /**
         * Configuration-chip URL on the brand bar; `null` when no manifest entry is available (chip renders disabled).
         */
        public string|null $configUrl,
        /**
         * Panels keyed by id; consumed by the sidebar partial when `$useShell` is true.
         *
         * @var array<string, Panel>
         */
        public array $shellPanels,
        /**
         * Manifest entries (reverse-ordered, newest first) consumed by the sidebar.
         *
         * @var array<string, array<string, mixed>>
         */
        public array $shellManifest,
        /**
         * Active panel highlighted in the sidebar nav; `null` in index/bare mode.
         */
        public Panel|null $activePanel,
        /**
         * Active request tag; `null` in index/bare mode.
         */
        public string|null $activeTag,
        /**
         * Active request summary ('method/url/status/time/ajax'); `null` in index/bare mode.
         *
         * @var array<string, mixed>|null
         */
        public array|null $shellSummary,
        /**
         * Optional cursor-init tag (`?cursor=<tag>`) the index JS should land on.
         */
        public string $cursorInit,
    ) {}
}
