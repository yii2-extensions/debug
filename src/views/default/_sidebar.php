<?php

declare(strict_types=1);

use yii\debug\widgets\NavigationButton;
use yii\helpers\Html;

/**
 * Shell sidebar partial — shared between view.php and index.php so the navigation chrome is
 * defined in one place. The `$mode` arg switches between two layouts that share the same
 * snapshot card so the sidebar feels like one debugger across every route:
 *
 *   - `'view'` → snapshot of the active request + Prev/Next/Latest/Last 10 navigator,
 *     and the panels nav highlights the active panel.
 *   - `'index'` → snapshot of the *latest* captured request + Latest/Last 10 (Prev/Next
 *     don't apply because there is no cursor), and the History entry of the panels nav is
 *     active. The aggregate overview (count, status-bucket pills) lives as a top summary
 *     strip in `index.php`.
 *
 * @var \yii\web\View $this
 * @var string $mode `'view'` or `'index'`.
 * @var \yii\debug\Panel[] $panels Panels keyed by id; iterated to build the nav.
 * @var array $manifest Reverse-ordered (newest first) tag => summary map.
 *
 * `'view'`-only inputs:
 * @var \yii\debug\Panel|null $activePanel Active panel (used for highlight + history dropdown URL).
 * @var string|null $tag Active request tag.
 * @var array|null $summary Active request summary (method/url/status/time/ajax).
 *
 * `'index'`-only inputs:
 * @var string $cursorInit Initial tag for the cursor JS — preserves context when arriving from
 *      a panel view's "History" link (`?cursor=<tag>`). Empty string falls back to the latest.
 */

$mode = $mode ?? 'view';

$latestTag = $manifest === [] ? null : array_key_first($manifest);

// Snapshot data: in view mode we surface the active request, in index mode the latest one.
// `$snapshotSummary` may stay null when the manifest is empty — we guard the whole card on it.
if ($mode === 'view') {
    /** @var \yii\debug\Panel $activePanel */
    /** @var string $tag */
    /** @var array $summary */
    $snapshotTag = $tag;
    $snapshotSummary = $summary;
} else {
    $snapshotTag = $latestTag;
    $snapshotSummary = $latestTag !== null ? ($manifest[$latestTag] ?? null) : null;
}

// Pick a panel for the navigator URLs. In view mode it's the active panel; in index mode we
// fall back to the Request panel (or the first available) so Prev/Next still work, anchored on
// the latest tag. Without a panel, NavigationButton can't build a URL → we skip the row.
$navPanel = ($activePanel ?? null)
    ?? ($panels['request'] ?? null)
    ?? (is_array($panels) && $panels !== [] ? reset($panels) : null);
$snapshotPanelId = $navPanel?->id;
$showPrevNext = $navPanel !== null && $navPanel->hasRequestNavigation();
$showCard = $snapshotTag !== null && is_array($snapshotSummary);

if ($showCard) {
    $currentStatus = (int) ($snapshotSummary['statusCode'] ?? 0);
    $statusVariant = match (true) {
        $currentStatus >= 500 => 'danger',
        $currentStatus >= 400 => 'warning',
        $currentStatus >= 300 => 'muted',
        $currentStatus >= 200 => 'success',
        default => 'muted',
    };

    // Endpoint and step URLs for the navigator. Button labels read the GridView positionally —
    // `First` = first row (top of list, newest captured); `Latest` = last row (bottom of list,
    // oldest captured). The manifest is reverse-ordered (newest first), so `topTag` is the
    // first key and `bottomTag` is the last. Prev/Next step toward newer/older neighbours.
    $topTag = $latestTag;
    $bottomTag = array_key_last($manifest);
    $manifestKeys = array_keys($manifest);
    $cursorIndex = array_search($snapshotTag, $manifestKeys, true);
    $prevTag = is_int($cursorIndex) && $cursorIndex > 0 ? $manifestKeys[$cursorIndex - 1] : null;
    $nextTag = is_int($cursorIndex) && $cursorIndex < count($manifestKeys) - 1
        ? $manifestKeys[$cursorIndex + 1]
        : null;

    $buildUrl = static function (string|null $tag) use ($snapshotPanelId): array {
        $url = ['view'];
        if ($tag !== null) {
            $url['tag'] = $tag;
        }
        if ($snapshotPanelId !== null) {
            $url['panel'] = $snapshotPanelId;
        }
        return $url;
    };

    // First → top of list (no tag, controller defaults to latest); Latest → bottom of list.
    $firstUrl = $buildUrl(null);
    $latestUrl = $buildUrl($bottomTag);
    $prevUrl = $prevTag !== null ? $buildUrl($prevTag) : '';
    $nextUrl = $nextTag !== null ? $buildUrl($nextTag) : '';

    $onFirst = $snapshotTag === $topTag;
    $onLatest = $snapshotTag === $bottomTag;

    $sectionTitle = $mode === 'view' ? 'Current request' : 'Latest request';
    $sectionAriaLabel = $mode === 'view' ? 'Current request' : 'Latest captured request';
}
?>
<aside class="yii-debug-sidebar">
    <?php if ($showCard): ?>
        <?php
        // In index mode the section becomes a "cursor" controller — Prev/Next/Latest/Last 10
        // move a highlight through the GridView rows and restamp the card from each row's
        // `data-yii-debug-*` payload, no navigation. The marker attribute is what the inline
        // script in `index.php` looks for to wire the bridge.
        // `data-yii-debug-cursor-init` carries the optional tag the JS should land on (passed
        // via `?cursor=<tag>` from a panel view's "History" link, so the developer doesn't
        // lose the request they were inspecting).
        $isCursor = $mode === 'index';
        $cursorAttr = $isCursor ? ' data-yii-debug-history-cursor' : '';
        $cursorInitTag = $isCursor && isset($cursorInit) && is_string($cursorInit) && $cursorInit !== ''
            ? $cursorInit
            : '';
        if ($cursorInitTag !== '') {
            $cursorAttr .= ' data-yii-debug-cursor-init="' . Html::encode($cursorInitTag) . '"';
        }

        // Helper that strips the scheme/host/port from a captured URL — the snapshot
        // card only has room for the meaningful part (path + query). The host part
        // is rarely useful in dev anyway and the LATEST/CURRENT request cards stay
        // visually consistent (issue #23). `php yii ...` console invocations are
        // passed through verbatim because they don't have a host portion.
        $toPath = static function (string $url): string {
            if ($url === '' || str_starts_with($url, 'php yii ')) {
                return $url;
            }
            $parsed = parse_url($url);
            if ($parsed === false) {
                return $url;
            }
            $path = (string) ($parsed['path'] ?? '/');
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
            $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#' . $parsed['fragment'] : '';
            return $path . $query . $fragment;
        };
        $snapshotPath = $toPath((string) ($snapshotSummary['url'] ?? ''));
        ?>
        <section class="yii-debug-side-section yii-debug-request-nav"
                 aria-label="<?= Html::encode($sectionAriaLabel) ?>"<?= $cursorAttr ?>>
            <header class="yii-debug-side-section-title"><?= Html::encode($sectionTitle) ?></header>

            <div class="yii-debug-history-card" title="<?= Html::encode(($snapshotSummary['method'] ?? '') . ' ' . ($snapshotSummary['url'] ?? '')) ?>">
                <div class="yii-debug-snapshot-line">
                    <span class="yii-debug-snapshot-method" data-snapshot-field="method"><?= Html::encode($snapshotSummary['method'] ?? '') ?></span>
                    <span class="yii-debug-snapshot-url" data-snapshot-field="url" title="<?= Html::encode((string) ($snapshotSummary['url'] ?? '')) ?>"><?= Html::encode($snapshotPath) ?></span>
                </div>
                <div class="yii-debug-snapshot-meta">
                    <span class="yii-debug-snapshot-status yii-debug-snapshot-status-<?= $statusVariant ?>"
                          data-snapshot-field="status"><?= $currentStatus ?: '–' ?></span>
                    <span class="yii-debug-snapshot-time" data-snapshot-field="time"<?= empty($snapshotSummary['time']) ? ' hidden' : '' ?>>
                        <?= !empty($snapshotSummary['time']) ? date('H:i:s', (int) $snapshotSummary['time']) : '' ?>
                    </span>
                    <span class="yii-debug-snapshot-tag" data-snapshot-field="ajax"<?= empty($snapshotSummary['ajax']) ? ' hidden' : '' ?>>AJAX</span>
                </div>

                <?php
                // Single-row navigator: First | Prev | Next | Latest. Labels are encoded as
                // Tabler chevrons matching the cursor direction in the GridView (newest at top,
                // oldest at bottom): chevrons-up = jump to top, chevron-up = step up (newer),
                // chevron-down = step down (older), chevrons-down = jump to bottom. Tooltips
                // (`title` + `aria-label`) keep the textual meaning available for hover and AT.
                $iconFirst = $inlineSvg('chevrons-up.svg');
        $iconPrev = $inlineSvg('chevron-up.svg');
        $iconNext = $inlineSvg('chevron-down.svg');
        $iconLatest = $inlineSvg('chevrons-down.svg');
        $iconBtnClass = 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon';
        ?>
                <div class="yii-debug-request-nav-row" role="group">
                    <?php if ($isCursor): ?>
                        <button type="button" class="<?= $iconBtnClass ?> is-disabled"
                                data-yii-debug-cursor="first" title="First (top of list)" aria-label="First (top of list)"><?= $iconFirst ?></button>
                        <button type="button" class="<?= $iconBtnClass ?> is-disabled"
                                data-yii-debug-cursor="prev" title="Previous (newer)" aria-label="Previous (newer)"><?= $iconPrev ?></button>
                        <button type="button" class="<?= $iconBtnClass ?>"
                                data-yii-debug-cursor="next" title="Next (older)" aria-label="Next (older)"><?= $iconNext ?></button>
                        <button type="button" class="<?= $iconBtnClass ?>"
                                data-yii-debug-cursor="latest" title="Latest (bottom of list)" aria-label="Latest (bottom of list)"><?= $iconLatest ?></button>
                    <?php else: ?>
                        <?= Html::a($iconFirst, $firstUrl, [
                            'class' => $iconBtnClass . ($onFirst ? ' is-disabled' : ''),
                            'title' => 'First (top of list)',
                            'aria-label' => 'First captured request',
                        ]) ?>
                        <?= Html::a($iconPrev, $prevTag !== null ? $prevUrl : '', [
                            'class' => $iconBtnClass . ($prevTag === null ? ' is-disabled' : ''),
                            'title' => 'Previous (newer)',
                            'aria-label' => 'Previous request',
                        ]) ?>
                        <?= Html::a($iconNext, $nextTag !== null ? $nextUrl : '', [
                            'class' => $iconBtnClass . ($nextTag === null ? ' is-disabled' : ''),
                            'title' => 'Next (older)',
                            'aria-label' => 'Next request',
                        ]) ?>
                        <?= Html::a($iconLatest, $latestUrl, [
                            'class' => $iconBtnClass . ($onLatest ? ' is-disabled' : ''),
                            'title' => 'Latest (bottom of list)',
                            'aria-label' => 'Latest captured request',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
        <?php
        $historyIcon = $inlineSvg('history.svg');
$historyClasses = ['yii-debug-nav-link'];
if ($mode === 'index') {
    $historyClasses[] = 'is-active';
} else {
    $historyClasses[] = 'yii-debug-nav-link-muted';
}
// Preserve the active tag when jumping to History from a panel view — the
// index reads the `cursor` query param to position its JS cursor on that
// row instead of snapping back to the latest capture.
$historyParams = ['index'];
if ($mode === 'view' && is_string($tag ?? null) && $tag !== '') {
    $historyParams['cursor'] = $tag;
}
?>
        <a class="<?= implode(' ', $historyClasses) ?>"
           href="<?= Html::encode(\yii\helpers\Url::to($historyParams)) ?>"
           title="Browse all captured requests"<?= $mode === 'index' ? ' aria-current="page"' : '' ?>>
            <?php if ($historyIcon !== ''): ?>
                <span class="yii-debug-nav-link-icon" aria-hidden="true"><?= $historyIcon ?></span>
            <?php endif; ?>
            <span class="yii-debug-nav-link-label">History</span>
        </a>

        <?php foreach ($panels as $id => $panel): ?>
            <?php
    // Configuration is promoted to the brand bar (it's a global concern: app config /
    // php.ini / version, not request-scoped) so we skip it here to avoid duplication.
    if ($id === 'config') {
        continue;
    }

            $iconKey = $panel->getToolbarIcon();
            $iconSvg = is_string($iconKey) && $iconKey !== ''
                ? $inlineSvg($iconKey . '.svg')
                : '';

            $isActive = $mode === 'view' && $panel === $activePanel;

            if ($mode === 'view') {
                $url = ['view', 'tag' => $tag, 'panel' => $id];
                $tooltip = $panel->getName();
            } elseif ($latestTag !== null) {
                $url = ['view', 'tag' => $latestTag, 'panel' => $id];
                $tooltip = 'Open this panel on the latest request';
            } else {
                $url = ['index'];
                $tooltip = 'Pick a request first';
            }

            $linkClasses = ['yii-debug-nav-link'];
            if ($isActive) {
                $linkClasses[] = 'is-active';
            } else {
                $linkClasses[] = 'yii-debug-nav-link-muted';
            }
            ?>
            <a class="<?= implode(' ', $linkClasses) ?>"
               href="<?= Html::encode(\yii\helpers\Url::to($url)) ?>"
               title="<?= Html::encode($tooltip) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <?php if ($iconSvg !== ''): ?>
                    <span class="yii-debug-nav-link-icon" aria-hidden="true"><?= $iconSvg ?></span>
                <?php endif; ?>
                <span class="yii-debug-nav-link-label"><?= Html::encode($panel->getName()) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
