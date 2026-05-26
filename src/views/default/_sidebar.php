<?php

declare(strict_types=1);

use yii\debug\Panel;
use yii\debug\widgets\sidebar\{SidebarDataNormalizer, SidebarRenderer};
use yii\web\View;

/**
 * @var Panel|null $activePanel Active panel for the current request view.
 * @var string $cursorInit Initial tag for the cursor JS; preserves context when arriving from a panel view's "History"
 * link (`?cursor=<tag>`). Empty string falls back to the latest.
 * @var array<int|string, mixed> $manifest Reverse-ordered (newest first) tag => summary map.
 * @var string $mode 'view' or 'index'.
 * @var array<int|string, mixed> $panels Panels keyed by id; iterated to build the nav.
 * @var array<string, mixed>|null $summary Active request summary (method/url/status/time/ajax).
 * @var string|null $tag Active request tag.
 * @var View $this View component instance.
 */
?>
<?= SidebarRenderer::render(
    SidebarDataNormalizer::fromShell($mode, $panels, $manifest, $activePanel, $tag, $summary, $cursorInit),
);
