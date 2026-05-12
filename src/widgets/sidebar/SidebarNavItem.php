<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

/**
 * Typed view-model for one entry in the debugger sidebar panel navigation.
 *
 * Encapsulates the per-panel resolution (icon SVG, URL parameters, tooltip text, active-state flag) so the renderer
 * stays focused on emitting markup. Used for both the 'History' entry and every registered panel link.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class SidebarNavItem
{
    public function __construct(
        /**
         * Visible label shown next to the icon ('History', 'Request', 'Database', ...).
         */
        public string $label,
        /**
         * Pre-rendered SVG glyph for the link icon; empty string when the panel did not supply a toolbar icon.
         */
        public string $iconSvg,
        /**
         * URL parameters consumed by {@see \yii\helpers\Url::to()} for the `href` attribute.
         *
         * @var array<int|string, string>
         */
        public array $url,
        /**
         * `title` / `aria-label` hover text.
         */
        public string $tooltip,
        /**
         * `true` when this entry represents the currently active route — drives the `is-active` modifier and the
         * `aria-current="page"` attribute on the rendered `<a>`.
         */
        public bool $isActive,
    ) {}
}
