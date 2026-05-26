<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

use yii\debug\helpers\Icon;
use yii\debug\Panel;

use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_search;
use function count;
use function date;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function parse_url;
use function reset;
use function str_starts_with;

/**
 * Narrows the loose `_sidebar.php` inputs (panels map + manifest + active panel + summary) into the typed
 * {@see SidebarView}.
 *
 * Concentrates every defensive {@see is_array()} / {@see is_string()} / {@see is_int()} check that previously lived
 * inline in the 285-line partial. The two modes the sidebar supports are exposed as named factory methods:
 *
 * - `fromView(...)` used by panel `view` requests; surfaces the active request snapshot and highlights the active panel
 *   in the nav.
 * - `fromIndex(...)`used by `index` requests; surfaces the latest captured request and highlights the History entry.
 */
final class SidebarDataNormalizer
{
    /**
     * Builds the typed sidebar view-model for `index.php` (history grid). Surfaces the latest captured request as the
     * snapshot, marks the History entry as active, and wires the navigator buttons as a GridView cursor.
     *
     * @param array<string, Panel> $panels
     * @param array<string, array<string, mixed>> $manifest
     */
    public static function fromIndex(array $panels, array $manifest, string $cursorInit = ''): SidebarView
    {
        $latestTag = self::latestTag($manifest);

        $snapshotSummary = $latestTag !== null ? ($manifest[$latestTag] ?? null) : null;

        $snapshot = self::buildSnapshot(
            mode: 'index',
            panels: $panels,
            manifest: $manifest,
            activePanel: null,
            snapshotTag: $latestTag,
            snapshotSummary: is_array($snapshotSummary) ? $snapshotSummary : null,
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
     * Builds the typed sidebar view-model from the loose `_sidebar.php` inputs, narrowing the panel and manifest maps
     * and dispatching to {@see fromView()} or {@see fromIndex()} based on the request mode.
     *
     * @param array<int|string, mixed> $panels
     * @param array<int|string, mixed> $manifest
     * @param array<string, mixed>|null $summary
     */
    public static function fromShell(
        string $mode,
        array $panels,
        array $manifest,
        Panel|null $activePanel,
        string|null $tag,
        array|null $summary,
        string $cursorInit = '',
    ): SidebarView {
        $narrowedPanels = self::narrowPanels($panels);
        $narrowedManifest = self::narrowManifest($manifest);

        if ($mode === 'view' && $activePanel !== null && $tag !== null && $summary !== null) {
            return self::fromView($narrowedPanels, $narrowedManifest, $activePanel, $tag, $summary);
        }

        return self::fromIndex($narrowedPanels, $narrowedManifest, $cursorInit);
    }

    /**
     * Builds the typed sidebar view-model for panel `view` requests. Surfaces the active request snapshot and
     * highlights the active panel in the nav.
     *
     * @param array<string, Panel> $panels
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, mixed> $summary
     */
    public static function fromView(
        array $panels,
        array $manifest,
        Panel $activePanel,
        string $tag,
        array $summary,
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
     * @param array<string, array<string, mixed>> $manifest
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
        $latestTag = self::latestTag($manifest);

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

            $iconKey = $panel->getToolbarIcon();
            $iconSvg = is_string($iconKey) && $iconKey !== '' ? Icon::render($iconKey) : '';

            $isActive = $mode === 'view' && $panel === $activePanel;

            if ($mode === 'view' && $activeTag !== null) {
                $url = ['view', 'tag' => $activeTag, 'panel' => $id];

                $tooltip = $panel->getName();
            } elseif ($latestTag !== null) {
                $url = ['view', 'tag' => $latestTag, 'panel' => $id];
                $tooltip = 'Open this panel on the latest request';
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
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, mixed>|null $snapshotSummary
     */
    private static function buildSnapshot(
        string $mode,
        array $panels,
        array $manifest,
        Panel|null $activePanel,
        string|null $snapshotTag,
        array|null $snapshotSummary,
        string $cursorInit,
    ): SidebarSnapshot|null {
        if ($snapshotTag === null || $snapshotSummary === null) {
            return null;
        }

        $navPanel = $activePanel
            ?? ($panels['request'] ?? null)
            ?? ($panels !== [] ? reset($panels) : null);

        $snapshotPanelId = $navPanel !== null ? $navPanel->id : null;

        $statusCode = is_numeric($snapshotSummary['statusCode'] ?? null)
            ? (int) $snapshotSummary['statusCode']
            : 0;

        $method = is_string($snapshotSummary['method'] ?? null) ? $snapshotSummary['method'] : '';
        $fullUrl = is_string($snapshotSummary['url'] ?? null) ? $snapshotSummary['url'] : '';

        $time = self::formatTime($snapshotSummary['time'] ?? null);

        $ajaxValue = $snapshotSummary['ajax'] ?? null;
        $isAjax = $ajaxValue !== null && $ajaxValue !== false && $ajaxValue !== 0 && $ajaxValue !== '';

        $topTag = self::latestTag($manifest);

        $bottomTag = array_key_last($manifest);
        $manifestKeys = array_keys($manifest);
        $cursorIndex = array_search($snapshotTag, $manifestKeys, true);

        $prevTag = is_int($cursorIndex) && $cursorIndex > 0 && isset($manifestKeys[$cursorIndex - 1])
            ? $manifestKeys[$cursorIndex - 1]
            : null;
        $nextTag = is_int($cursorIndex) && $cursorIndex < count($manifestKeys) - 1 && isset($manifestKeys[$cursorIndex + 1])
            ? $manifestKeys[$cursorIndex + 1]
            : null;

        return new SidebarSnapshot(
            title: $mode === 'view' ? 'Current request' : 'Latest request',
            ariaLabel: $mode === 'view' ? 'Current request' : 'Latest captured request',
            method: $method,
            path: self::urlToPath($fullUrl),
            fullUrl: $fullUrl,
            statusCode: $statusCode,
            statusVariant: self::statusVariant($statusCode),
            time: $time,
            isAjax: $isAjax,
            isCursor: $mode === 'index',
            cursorInitTag: $cursorInit,
            firstUrl: self::buildUrl(null, $snapshotPanelId),
            latestUrl: self::buildUrl($bottomTag, $snapshotPanelId),
            prevUrl: $prevTag !== null ? self::buildUrl($prevTag, $snapshotPanelId) : [],
            nextUrl: $nextTag !== null ? self::buildUrl($nextTag, $snapshotPanelId) : [],
            onFirst: $snapshotTag === $topTag,
            onLatest: $snapshotTag === $bottomTag,
            hasPrev: $prevTag !== null,
            hasNext: $nextTag !== null,
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

    private static function formatTime(mixed $time): string
    {
        if (!is_numeric($time)) {
            return '';
        }

        $unix = (int) $time;

        return $unix > 0 ? date('H:i:s', $unix) : '';
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     */
    private static function latestTag(array $manifest): string|null
    {
        if ($manifest === []) {
            return null;
        }

        return array_key_first($manifest);
    }

    /**
     * Narrows a loose manifest map to the typed `array<string, array<string, mixed>>` shape.
     *
     * @param array<int|string, mixed> $manifest
     *
     * @return array<string, array<string, mixed>>
     */
    private static function narrowManifest(array $manifest): array
    {
        $narrowed = [];

        foreach ($manifest as $tag => $entry) {
            if (is_string($tag) && is_array($entry)) {
                $stringKeyed = [];

                foreach ($entry as $key => $value) {
                    if (is_string($key)) {
                        $stringKeyed[$key] = $value;
                    }
                }

                $narrowed[$tag] = $stringKeyed;
            }
        }

        return $narrowed;
    }

    /**
     * Narrows a loose panel map to the typed `array<string, Panel>` shape.
     *
     * @param array<int|string, mixed> $panels
     *
     * @return array<string, Panel>
     */
    private static function narrowPanels(array $panels): array
    {
        $narrowed = [];

        foreach ($panels as $id => $panel) {
            if (is_string($id) && $panel instanceof Panel) {
                $narrowed[$id] = $panel;
            }
        }

        return $narrowed;
    }

    private static function statusVariant(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'danger',
            $statusCode >= 400 => 'warning',
            $statusCode >= 300 => 'muted',
            $statusCode >= 200 => 'success',
            default => 'muted',
        };
    }

    /**
     * Strips the scheme/host/port from a captured URL so the snapshot card only shows the meaningful path + query +
     * fragment. `php yii ...` console invocations pass through verbatim since they have no host portion.
     */
    private static function urlToPath(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'php yii ')) {
            return $url;
        }

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
