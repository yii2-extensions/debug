<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Encode;
use yii\debug\Panel;
use yii\debug\panels\queue\{JobRecord, QueueCardRenderer};
use yii\debug\panels\QueuePanel;
use yii\helpers\Url;

/**
 * @var Panel $activePanel
 * @var string $debugTheme
 * @var array<string, array<string, mixed>> $manifest
 * @var QueuePanel $panel
 * @var Panel[] $panels
 * @var JobRecord $record
 * @var array<string, mixed> $summary
 * @var string $tag
 * @var string $themeIconMoon
 * @var string $themeIconSun
 * @var \yii\web\View $this
 */

$this->title = 'Yii Debugger — Queue job';

// Wire the shell layout so the queue-job page renders inside the same chrome (brand bar + sidebar with debug-panel
// nav + history navigation) the main `view` action uses. Without this the page shows a bare card with no way back.
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
        <a class="yii-debug-btn yii-debug-btn-ghost" href="<?= Encode::value($backUrl) ?>">← Back to grid</a>
    </header>

    <?= QueueCardRenderer::renderItem($record) ?>
</div>
