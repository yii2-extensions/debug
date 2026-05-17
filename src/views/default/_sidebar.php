<?php

declare(strict_types=1);

use yii\debug\Panel;
use yii\debug\widgets\sidebar\{SidebarDataNormalizer, SidebarRenderer};
use yii\web\View;

/**
 * Shell sidebar partial; shared between view.php and index.php. The `$mode` arg switches between two layouts that share
 * the same snapshot card so the sidebar feels like one debugger across every route.
 *
 * @var string $cursorInit Initial tag for the cursor JS; preserves context when arriving from a panel view's "History"
 * link (`?cursor=<tag>`). Empty string falls back to the latest.
 * @var array<int|string, mixed> $manifest Reverse-ordered (newest first) tag => summary map.
 * @var string $mode 'view' or 'index'.
 * @var Panel|null $activePanel Active panel (used for highlight + history dropdown URL).
 * @var array<int|string, mixed> $panels Panels keyed by id; iterated to build the nav.
 * @var array<string, mixed>|null $summary Active request summary (method/url/status/time/ajax).
 * @var string|null $tag Active request tag.
 * @var View $this
 */

$narrowedPanels = [];

foreach ($panels as $id => $panel) {
    if (is_string($id) && $panel instanceof Panel) {
        $narrowedPanels[$id] = $panel;
    }
}

$narrowedManifest = [];

foreach ($manifest as $manifestTag => $entry) {
    if (is_string($manifestTag) && is_array($entry)) {
        $stringKeyedEntry = [];

        foreach ($entry as $entryKey => $entryValue) {
            if (is_string($entryKey)) {
                $stringKeyedEntry[$entryKey] = $entryValue;
            }
        }

        $narrowedManifest[$manifestTag] = $stringKeyedEntry;
    }
}

if ($mode === 'view' && $activePanel !== null && is_string($tag ?? null) && is_array($summary ?? null)) {
    $view = SidebarDataNormalizer::fromView($narrowedPanels, $narrowedManifest, $activePanel, $tag, $summary);
} else {
    $view = SidebarDataNormalizer::fromIndex($narrowedPanels, $narrowedManifest, $cursorInit);
}

echo SidebarRenderer::render($view);
