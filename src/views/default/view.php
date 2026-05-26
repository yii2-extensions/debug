<?php

declare(strict_types=1);

use yii\debug\Panel;
use yii\web\View;

/**
 * @var Panel $activePanel Active panel for the current request view.
 * @var string $debugTheme Theme passed in by DefaultController::primeThemeContext().
 * @var array<int|string, mixed> $manifest Reverse-ordered (newest first) tag-to-summary map.
 * @var Panel[] $panels Debug panels keyed by id.
 * @var array<string, mixed> $summary Active request summary (method, URL, status, time).
 * @var string $tag Active request tag.
 * @var string $themeIconMoon Pre-loaded moon glyph.
 * @var string $themeIconSun Pre-loaded sun glyph (read once on the controller).
 * @var View $this View component instance.
 */
$this->title = 'Yii Debugger';

// The layout owns the shell (brand bar + sidebar + main wrapper). We just declare the mode + payload it needs and emit
// the panel content; the layout fans it out to _shell_header and _sidebar in `mode='view'`.
$this->params['shellMode'] = 'view';
$this->params['shellData'] = [
    'panels' => $panels,
    'manifest' => $manifest,
    'activePanel' => $activePanel,
    'tag' => $tag,
    'summary' => $summary,
    'debugTheme' => $debugTheme,
    'themeIconSun' => $themeIconSun,
    'themeIconMoon' => $themeIconMoon,
];
?>
<?= $activePanel->getDetail();
