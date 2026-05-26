<?php

declare(strict_types=1);

use UIAwesome\Html\Palpable\A;
use yii\debug\Panel;
use yii\debug\panels\queue\{JobRecord, QueueCardRenderer};
use yii\debug\panels\QueuePanel;
use yii\helpers\Url;
use yii\web\View;

/**
 * @var Panel $activePanel Active panel for the current request view.
 * @var string $debugTheme Resolved theme key, `'light'` or `'dark'`.
 * @var array<string, array<string, mixed>> $manifest Reverse-ordered (newest first) tag-to-summary map.
 * @var QueuePanel $panel Queue panel providing the job data.
 * @var Panel[] $panels Debug panels keyed by id.
 * @var JobRecord $record Queue job record being displayed.
 * @var array<string, mixed> $summary Active request summary (method, URL, status, time).
 * @var string $tag Active request tag.
 * @var string $themeIconMoon Pre-loaded moon glyph.
 * @var string $themeIconSun Pre-loaded sun glyph from the controller.
 * @var View $this View component instance.
 */
$this->title = 'Yii Debugger — Queue job';

// Wire the shell layout so the queue-job page renders inside the same chrome (brand bar + sidebar with debug-panel nav
// + history navigation) the main `view` action uses. Without this the page shows a bare card with no way back.
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

$backUrl = Url::to(['view', 'tag' => $tag, 'panel' => 'queue']);
?>
<div class="yii-debug-queue-job-page">
    <header class="yii-debug-queue-job-head">
        <?= A::tag()
            ->class('yii-debug-btn yii-debug-btn-ghost')
            ->content('← Back to grid')
            ->href($backUrl) ?>
    </header>

    <?= QueueCardRenderer::renderItem($record) ?>
</div>
