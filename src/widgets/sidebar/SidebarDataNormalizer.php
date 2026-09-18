<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

use PHPForge\Debug\Helper\{Coerce, Icon, Text, Vocabulary};
use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Sidebar\{SidebarNavItem, SidebarSnapshot, SidebarView};
use PHPForge\Debug\View\ViewMessage;
use yii\debug\{ExtensionAvailability, Module, Panel};
use yii\debug\view\ViewMessage as AdapterMessage;
use yii\helpers\Url;

use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_search;
use function date;
use function is_int;
use function reset;

/**
 * Builds the Debug Core sidebar view-model used by history and request views, with every route already resolved
 * through {@see Url::to()}.
 *
 * - `fromView(...)` used by panel `view` requests; surfaces the active request snapshot and highlights the active panel
 *   in the nav.
 * - `fromIndex(...)`used by `index` requests; surfaces the newest captured request and highlights the History entry.
 * - `fromStandalone(...)` used by pages outside any capture, such as phpinfo; highlights nothing.
 */
final class SidebarDataNormalizer
{
    /**
     * Builds the sidebar view-model for `index.php` (history grid). Surfaces the newest captured request as the
     * snapshot, marks the History entry as active, and wires the navigator buttons as a GridView cursor.
     *
     * @param array<string, Panel> $panels Registered panels keyed by ID.
     * @param array<string, RequestSummary> $manifest Captured request summaries, newest first.
     * @param string $cursorInit Tag the cursor should land on; empty to start at the newest capture.
     *
     * @return SidebarView Sidebar view-model for the history grid.
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

        $navigation = self::buildNavigation(
            panels: $panels,
            manifest: $manifest,
            activePanel: null,
            activeTag: null,
            mode: 'index',
        );

        return new SidebarView(snapshot: $snapshot, navItems: $navigation['items'], navGroups: $navigation['groups']);
    }

    /**
     * Builds the sidebar view-model for a standalone debugger page such as phpinfo.
     *
     * The page belongs to no capture and to no panel, so the navigation highlights nothing and the snapshot card
     * surfaces the newest capture, keeping the reader one click from the panels.
     *
     * @param array<string, Panel> $panels Registered panels keyed by ID.
     * @param array<string, RequestSummary> $manifest Captured request summaries, newest first.
     *
     * @return SidebarView Sidebar view-model for the standalone page.
     */
    public static function fromStandalone(array $panels, array $manifest): SidebarView
    {
        $newestTag = self::newestTag($manifest);
        $snapshot = self::buildSnapshot(
            mode: 'view',
            panels: $panels,
            manifest: $manifest,
            activePanel: null,
            snapshotTag: $newestTag,
            snapshotSummary: $newestTag !== null ? ($manifest[$newestTag] ?? null) : null,
            cursorInit: '',
        );
        $navigation = self::buildNavigation(
            panels: $panels,
            manifest: $manifest,
            activePanel: null,
            activeTag: $newestTag,
            mode: 'view',
        );

        return new SidebarView(snapshot: $snapshot, navItems: $navigation['items'], navGroups: $navigation['groups']);
    }

    /**
     * Builds the sidebar view-model for panel `view` requests. Surfaces the active request snapshot and highlights the
     * active panel in the nav.
     *
     * @param array<string, Panel> $panels Registered panels keyed by ID.
     * @param array<string, RequestSummary> $manifest Captured request summaries, newest first.
     * @param Panel $activePanel Panel being inspected, highlighted in the navigation.
     * @param string $tag Tag of the capture under inspection.
     * @param RequestSummary $summary Summary of the capture under inspection.
     *
     * @return SidebarView Sidebar view-model for the panel view.
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
        $navigation = self::buildNavigation(
            panels: $panels,
            manifest: $manifest,
            activePanel: $activePanel,
            activeTag: $tag,
            mode: 'view',
        );

        return new SidebarView(snapshot: $snapshot, navItems: $navigation['items'], navGroups: $navigation['groups']);
    }

    /**
     * Builds the panel-list nav entries. History always comes first; the `config` panel is intentionally skipped so the
     * brand bar keeps the only Config CTA. Both the primary list and the Extensions group keep the display order the
     * module resolved through the shared registration policy.
     *
     * @param array<string, Panel> $panels Registered panels keyed by ID, in display order.
     * @param array<string, RequestSummary> $manifest Captured request summaries, newest first.
     * @param Panel|null $activePanel Panel to mark as active, or `null` to activate the History entry.
     * @param string|null $activeTag Tag carried over to the panel links, or `null` to link the newest capture.
     * @param string $mode Shell mode driving the active entry: `'view'` or `'index'`.
     *
     * @return array{items: list<SidebarNavItem>, groups: array<string, list<SidebarNavItem>>} Navigation structure for
     * the sidebar.
     */
    private static function buildNavigation(
        array $panels,
        array $manifest,
        Panel|null $activePanel,
        string|null $activeTag,
        string $mode,
    ): array {
        $newestTag = self::newestTag($manifest);

        $historyParams = Module::route('index');

        if ($mode === 'view' && $activeTag !== null && $activeTag !== '') {
            $historyParams['cursor'] = $activeTag;
        }

        $items = [
            new SidebarNavItem(
                label: PanelTitle::HISTORY->value,
                iconSvg: Icon::render('history'),
                url: Url::to($historyParams),
                tooltip: AdapterMessage::HISTORY_TOOLTIP->value,
                isActive: $mode === 'index',
            ),
        ];

        $extensionItems = [];

        foreach ($panels as $id => $panel) {
            if ($id === 'config' || !$panel->isVisible()) {
                continue;
            }

            if ($mode === 'view' && $panel->hasContent() === false && $panel->hasError() === false) {
                continue;
            }

            $iconKey = Coerce::string($panel->getToolbarIcon());
            $iconSvg = $iconKey !== '' ? Icon::render($iconKey) : '';

            $isActive = $mode === 'view' && $panel === $activePanel;

            if ($mode === 'view' && $activeTag !== null) {
                $url = Module::route('view', ['tag' => $activeTag, 'panel' => $id]);

                $tooltip = $panel->getName();
            } elseif ($newestTag !== null) {
                $url = Module::route('view', ['tag' => $newestTag, 'panel' => $id]);
                $tooltip = AdapterMessage::PANEL_TOOLTIP_NEWEST->value;
            } else {
                $url = Module::route('index');
                $tooltip = AdapterMessage::PANEL_TOOLTIP_NO_SELECTION->value;
            }

            $item = new SidebarNavItem(
                label: $panel->getName(),
                iconSvg: $iconSvg,
                url: Url::to($url),
                tooltip: $tooltip,
                isActive: $isActive,
            );

            if (ExtensionAvailability::isExtensionPanel($id, $panel)) {
                $extensionItems[] = $item;

                continue;
            }

            $items[] = $item;
        }

        return [
            'items' => $items,
            'groups' => $extensionItems === [] ? [] : [ViewMessage::EXTENSIONS->value => $extensionItems],
        ];
    }

    /**
     * Builds the snapshot card view-model. Returns `null` when the manifest is empty (the card section is skipped
     * altogether by the renderer).
     *
     * @param string $mode Shell mode driving the card heading: `'view'` or `'index'`.
     * @param array<string, Panel> $panels Registered panels keyed by ID.
     * @param array<string, RequestSummary> $manifest Captured request summaries, newest first.
     * @param Panel|null $activePanel Panel the navigator buttons should keep open, or `null` for the default.
     * @param string|null $snapshotTag Tag of the capture to surface, or `null` when the manifest is empty.
     * @param RequestSummary|null $snapshotSummary Summary of that capture, or `null` when the manifest is empty.
     * @param string $cursorInit Tag the cursor should land on; empty to start at the newest capture.
     *
     * @return SidebarSnapshot|null Snapshot card, or `null` when there is no capture to surface.
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
        $fullUrl = $snapshotSummary->url;

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

        $title = $mode === 'view' ? ViewMessage::CURRENT_REQUEST->value : ViewMessage::NEWEST_REQUEST->value;

        return SidebarSnapshot::create(
            $title,
            $mode === 'view' ? $title : ViewMessage::NEWEST_CAPTURED_REQUEST->value,
        )
            ->withRequest(
                $snapshotSummary->method,
                Text::urlToPath($fullUrl),
                $fullUrl,
                self::formatTime($snapshotSummary->time),
                $snapshotSummary->ajax,
            )
            ->withResponse($statusCode, self::statusVariant($statusCode))
            ->withCursor($mode === 'index', $cursorInit)
            ->withNavigationUrls(
                self::buildUrl($topTag, $snapshotPanelId),
                self::buildUrl($bottomTag, $snapshotPanelId),
                $prevTag !== null ? self::buildUrl($prevTag, $snapshotPanelId) : '',
                $nextTag !== null ? self::buildUrl($nextTag, $snapshotPanelId) : '',
            )
            ->withNavigationState(
                $snapshotTag === $topTag,
                $snapshotTag === $bottomTag,
                $prevTag !== null,
                $nextTag !== null,
            );
    }

    /**
     * Resolves the navigator endpoint for a capture.
     *
     * @param string|null $tag Capture to open, or `null` to omit the parameter.
     * @param string|null $panelId Panel to open, or `null` to omit the parameter.
     *
     * @return string Resolved URL of the target view.
     */
    private static function buildUrl(string|null $tag, string|null $panelId): string
    {
        $url = Module::route('view');

        if ($tag !== null) {
            $url['tag'] = $tag;
        }

        if ($panelId !== null) {
            $url['panel'] = $panelId;
        }

        return Url::to($url);
    }

    /**
     * Formats a capture timestamp as a time of day.
     *
     * @param float $time Capture timestamp as a Unix time.
     *
     * @return string Time of day as `HH:MM:SS`, or `''` when the timestamp was not captured.
     */
    private static function formatTime(float $time): string
    {
        $unix = (int) $time;

        return $unix > 0 ? date('H:i:s', $unix) : '';
    }

    /**
     * Returns the tag of the newest captured request.
     *
     * @param array<string, RequestSummary> $manifest Captured request summaries, newest first.
     *
     * @return string|null Tag of the newest capture, or `null` when the manifest is empty.
     */
    private static function newestTag(array $manifest): string|null
    {
        return array_key_first($manifest);
    }

    /**
     * Maps a response status code to its status-pill modifier.
     *
     * @param int $statusCode Response status code of the capture.
     *
     * @return string Status-pill CSS modifier for that code.
     */
    private static function statusVariant(int $statusCode): string
    {
        return Vocabulary::statusClass($statusCode);
    }
}
