<?php

declare(strict_types=1);

namespace yii\debug\widgets\shell;

use yii\debug\widgets\sidebar\SidebarView;

/**
 * Typed view-model for the debugger shell (brand bar + sidebar + main wrapper).
 *
 * The `$mode` discriminator picks the panel, history, or bare layout. The controller supplies fully normalized header
 * and sidebar data so the layout only renders this object.
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
         * Document attributes applied to `<html>`, including the language and resolved light/dark theme.
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
         * Sidebar view-model, or `null` for bare pages.
         */
        public SidebarView|null $sidebar,
    ) {}
}
