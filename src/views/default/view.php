<?php

declare(strict_types=1);

/** @var \yii\web\View $this */
/** @var array $summary */
/** @var string $tag */
/** @var array $manifest */
/** @var \yii\debug\Panel[] $panels */
/** @var \yii\debug\Panel $activePanel */
/** @var string $debugTheme Theme passed in by DefaultController::primeThemeContext(). */
/** @var string $themeIconSun Pre-loaded sun glyph (read once on the controller). */
/** @var string $themeIconMoon Pre-loaded moon glyph. */

$this->title = 'Yii Debugger';

// The layout owns the shell (brand bar + sidebar + main wrapper). We just
// declare the mode + payload it needs and emit the panel content; the
// layout fans it out to _shell_header and _sidebar in `mode='view'`.
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
<?= $activePanel->getDetail() ?>
