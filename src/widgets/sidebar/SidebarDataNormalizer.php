<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

use PHPForge\Debug\Helper\{Coerce, Icon, Vocabulary};
use PHPForge\Debug\Storage\RequestSummary;
use yii\debug\Panel;

use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_search;
use function date;
use function is_int;
use function is_string;
use function parse_url;
use function reset;

/**
 * Builds the typed sidebar data used by history and request views.
 *
 * - `fromView(...)` used by panel `view` requests; surfaces the active request snapshot and highlights the active panel
 *   in the nav.
 * - `fromIndex(...)`used by `index` requests; surfaces the newest captured request and highlights the History entry.
 */
final class SidebarDataNormalizer
{
    /**
     * Builds the typed sidebar view-model for `index.php` (history grid). Surfaces the newest captured request as the
     * snapshot, marks the History entry as active, and wires the navigator buttons as a GridView cursor.
     *
     * @param array<string, Panel> $panels
     * @param array<string, RequestSummary> $manifest
     */
    public static function fromIndex(array $panels, array $manifest, string $cursorInit = ''): SidebarView
    {
        $newestTag = self::newestTag($manifest);

        $snapshotSummary = $newestTag !== null ? ($manifest[$newestTag] ?? null) : null;

        $snapshot = self::buildSnapshot(
            mode: 'index',
            panels: $panels,
            manifest: $manifest,
            activePanel: null,
            snapshotTag: $newestTag,
            snapshotSummary: $snapshotSummary,
            cursorInit: $cursorInit,
        );

        return new SidebarView(
            snapshot: $snapshot,
            navItems: self::buildNavItems(
                panels: $panels,
                manifest: $manifest,
                activePanel: null,
                activeTag: null,
                mode: 'index',
            ),
        );
    }

    /**
     * Builds the typed sidebar view-model for panel `view` requests. Surfaces the active request snapshot and
     * highlights the active panel in the nav.
     *
     * @param array<string, Panel> $panels
     * @param array<string, RequestSummary> $manifest
     */
    public static function fromView(
        array $panels,
        array $manifest,
        Panel $activePanel,
        string $tag,
        RequestSummary $summary,
    ): SidebarView {
        $snapshot = self::buildSnapshot(
            mode: 'view',
            panels: $panels,
            manifest: $manifest,
            activePanel: $activePanel,
            snapshotTag: $tag,
            snapshotSummary: $summary,
            cursorInit: '',
        );

        return new SidebarView(
            snapshot: $snapshot,
            navItems: self::buildNavItems(
                panels: $panels,
                manifest: $manifest,
                activePanel: $activePanel,
                activeTag: $tag,
                mode: 'view',
            ),
        );
    }

    /**
     * Builds the panel-list nav entries. History always comes first; the `config` panel is intentionally skipped so the
     * brand bar keeps the only Config CTA.
     *
     * @param array<string, Panel> $panels
     * @param array<string, RequestSummary> $manifest
     *
     * @return list<SidebarNavItem>
     */
    private static function buildNavItems(
        array $panels,
        array $manifest,
        Panel|null $activePanel,
        string|null $activeTag,
        string $mode,
    ): array {
        $newestTag = self::newestTag($manifest);

        $historyParams = ['index'];

        if ($mode === 'view' && $activeTag !== null && $activeTag !== '') {
            $historyParams['cursor'] = $activeTag;
        }

        $items = [
            new SidebarNavItem(
                label: 'History',
                iconSvg: Icon::render('history'),
                url: $historyParams,
                tooltip: 'Browse all captured requests',
                isActive: $mode === 'index',
            ),
        ];

        foreach ($panels as $id => $panel) {
            if ($id === 'config') {
                continue;
            }

            if ($mode === 'view' && $panel->hasContent() === false) {
                continue;
            }

            $iconKey = Coerce::string($panel->getToolbarIcon());
            $iconSvg = $iconKey !== '' ? Icon::render($iconKey) : '';

            $isActive = $mode === 'view' && $panel === $activePanel;

            if ($mode === 'view' && $activeTag !== null) {
                $url = ['view', 'tag' => $activeTag, 'panel' => $id];

                $tooltip = $panel->getName();
            } elseif ($newestTag !== null) {
                $url = ['view', 'tag' => $newestTag, 'panel' => $id];
                $tooltip = 'Open this panel on the newest request';
            } else {
                $url = ['index'];
                $tooltip = 'Pick a request first';
            }

            $items[] = new SidebarNavItem(
                label: $panel->getName(),
                iconSvg: $iconSvg,
                url: $url,
                tooltip: $tooltip,
                isActive: $isActive,
            );
        }

        return $items;
    }

    /**
     * Builds the snapshot card view-model. Returns `null` when the manifest is empty (the card section is skipped
     * altogether by the renderer).
     *
     * @param array<string, Panel> $panels
     * @param array<string, RequestSummary> $manifest
     */
    private static function buildSnapshot(
        string $mode,
        array $panels,
        array $manifest,
        Panel|null $activePanel,
        string|null $snapshotTag,
        RequestSummary|null $snapshotSummary,
        string $cursorInit,
    ): SidebarSnapshot|null {
        if ($snapshotTag === null || $snapshotSummary === null) {
            return null;
        }

        $navPanel = $activePanel
            ?? ($panels['request'] ?? null)
            ?? ($panels !== [] ? reset($panels) : null);

        $snapshotPanelId = $navPanel !== null ? $navPanel->id : null;

        $statusCode = $snapshotSummary->statusCode;
        $method = $snapshotSummary->method;
        $fullUrl = $snapshotSummary->url;
        $time = self::formatTime($snapshotSummary->time);
        $isAjax = $snapshotSummary->ajax;

        $topTag = self::newestTag($manifest);

        $bottomTag = array_key_last($manifest);
        $manifestKeys = array_keys($manifest);
        $cursorIndex = array_search($snapshotTag, $manifestKeys, true);

        $prevTag = is_int($cursorIndex) && $cursorIndex > 0 && isset($manifestKeys[$cursorIndex - 1])
            ? $manifestKeys[$cursorIndex - 1]
            : null;
        $nextTag = is_int($cursorIndex) && isset($manifestKeys[$cursorIndex + 1])
            ? $manifestKeys[$cursorIndex + 1]
            : null;

        return new SidebarSnapshot(
            title: $mode === 'view' ? 'Current request' : 'Newest request',
            ariaLabel: $mode === 'view' ? 'Current request' : 'Newest captured request',
            method: $method,
            path: self::urlToPath($fullUrl),
            fullUrl: $fullUrl,
            statusCode: $statusCode,
            statusVariant: self::statusVariant($statusCode),
            time: $time,
            isAjax: $isAjax,
            isCursor: $mode === 'index',
            cursorInitTag: $cursorInit,
            newestUrl: self::buildUrl($topTag, $snapshotPanelId),
            oldestUrl: self::buildUrl($bottomTag, $snapshotPanelId),
            newerUrl: $prevTag !== null ? self::buildUrl($prevTag, $snapshotPanelId) : [],
            olderUrl: $nextTag !== null ? self::buildUrl($nextTag, $snapshotPanelId) : [],
            isNewest: $snapshotTag === $topTag,
            isOldest: $snapshotTag === $bottomTag,
            hasNewer: $prevTag !== null,
            hasOlder: $nextTag !== null,
        );
    }

    /**
     * Composes the URL parameters consumed by `Url::to()` for the navigator endpoints.
     *
     * @return array<int|string, string>
     */
    private static function buildUrl(string|null $tag, string|null $panelId): array
    {
        $url = ['view'];

        if ($tag !== null) {
            $url['tag'] = $tag;
        }

        if ($panelId !== null) {
            $url['panel'] = $panelId;
        }

        return $url;
    }

    private static function formatTime(float $time): string
    {
        $unix = (int) $time;

        return $unix > 0 ? date('H:i:s', $unix) : '';
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    private static function newestTag(array $manifest): string|null
    {
        return array_key_first($manifest);
    }

    private static function statusVariant(int $statusCode): string
    {
        return Vocabulary::statusClass($statusCode);
    }

    /**
     * Strips the scheme/host/port from a captured URL so the snapshot card only shows the meaningful path + query +
     * fragment. Console invocations pass through verbatim because `parse_url()` treats them as a path.
     */
    private static function urlToPath(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return $url;
        }

        $path = is_string($parsed['path'] ?? null) ? $parsed['path'] : '/';
        $query = is_string($parsed['query'] ?? null) && $parsed['query'] !== ''
            ? '?' . $parsed['query']
            : '';
        $fragment = is_string($parsed['fragment'] ?? null) && $parsed['fragment'] !== ''
            ? '#' . $parsed['fragment']
            : '';

        return "{$path}{$query}{$fragment}";
    }
}
