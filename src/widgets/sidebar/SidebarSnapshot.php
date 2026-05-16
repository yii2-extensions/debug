<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

/**
 * Typed view-model for the snapshot card surfaced at the top of the debugger sidebar ('CURRENT REQUEST' /
 * 'LATEST REQUEST').
 *
 * Pre-resolves every loose-array access on the request summary ('method', 'url', 'statusCode', 'time', 'ajax') plus
 * the navigation URLs ('First' / 'Prev' / 'Next' / 'Latest'), the status-pill variant, the path-only URL display, and
 * the cursor-mode flag that the `index.php` JS bridge needs to wire the GridView highlight.
 */
final readonly class SidebarSnapshot
{
    public function __construct(
        /**
         * Section heading shown above the snapshot card ('Current request' / 'Latest request').
         */
        public string $title,
        /**
         * Accessible name for the surrounding `<section>` element.
         */
        public string $ariaLabel,
        /**
         * HTTP method ('GET', 'POST', ...). Empty when not captured.
         */
        public string $method,
        /**
         * Path-only URL display (scheme/host stripped). Empty when not captured.
         */
        public string $path,
        /**
         * Full URL captured in the request summary; used as the `title` hover on the URL chip.
         */
        public string $fullUrl,
        /**
         * Response status code; '0' when not captured.
         */
        public int $statusCode,
        /**
         * Status-pill CSS modifier ('success' / 'muted' / 'warning' / 'danger') derived from `$statusCode`.
         */
        public string $statusVariant,
        /**
         * Formatted request time ('HH:MM:SS'); empty when not captured.
         */
        public string $time,
        /**
         * Whether the captured request was an AJAX request; surfaces the 'AJAX' tag in the card meta strip.
         */
        public bool $isAjax,
        /**
         * `true` when the sidebar is rendered for `index.php` and the navigator buttons act as a GridView cursor.
         */
        public bool $isCursor,
        /**
         * Optional tag the cursor JS should land on when the sidebar arrives from a panel view's History link
         * (`?cursor=<tag>`). Empty string falls back to the latest captured request.
         */
        public string $cursorInitTag,
        /**
         * First request URL parameters (top of list, newest captured).
         *
         * @var array<int|string, string>
         */
        public array $firstUrl,
        /**
         * Latest request URL parameters (bottom of list, oldest captured).
         *
         * @var array<int|string, string>
         */
        public array $latestUrl,
        /**
         * Previous (newer) request URL parameters; empty array when the snapshot is already on the first row.
         *
         * @var array<int|string, string>
         */
        public array $prevUrl,
        /**
         * Next (older) request URL parameters; empty array when the snapshot is already on the last row.
         *
         * @var array<int|string, string>
         */
        public array $nextUrl,
        /**
         * `true` when the snapshot is the first (newest) captured request; disables the First button.
         */
        public bool $onFirst,
        /**
         * `true` when the snapshot is the latest (oldest) captured request; disables the Latest button.
         */
        public bool $onLatest,
        /**
         * `true` when there is a previous (newer) request available; controls the Prev button.
         */
        public bool $hasPrev,
        /**
         * `true` when there is a next (older) request available; controls the Next button.
         */
        public bool $hasNext,
    ) {}
}
