<?php

declare(strict_types=1);

use yii\debug\widgets\NavigationButton;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var array $summary */
/** @var string $tag */
/** @var array $manifest */
/** @var \yii\debug\Panel[] $panels */
/** @var \yii\debug\Panel $activePanel */

$this->title = 'Yii Debugger';

$svgRoot = dirname(__DIR__, 2) . '/assets/svg/';
$inlineSvg = static fn(string $name): string => is_file($svgRoot . $name)
    ? (string) file_get_contents($svgRoot . $name)
    : '';

$configData = isset($panels['config']) ? ($panels['config']->data ?? []) : [];
$yiiVersion = (string) ($configData['application']['yii'] ?? \Yii::getVersion());
$phpVersion = (string) ($configData['php']['version'] ?? PHP_VERSION);
$peakMemory = isset($summary['peakMemory']) ? sprintf('%.2f MB', $summary['peakMemory'] / 1024 / 1024) : null;

// Resolve a status-code variant so the chip in the sidebar header matches the rest of the panel.
$currentStatus = (int) ($summary['statusCode'] ?? 0);
$statusVariant = match (true) {
    $currentStatus >= 500 => 'danger',
    $currentStatus >= 400 => 'warning',
    $currentStatus >= 300 => 'muted',
    $currentStatus >= 200 => 'success',
    default => 'muted',
};

$historyItems = [];
$count = 0;
foreach ($manifest as $meta) {
    $label = ($meta['tag'] === $tag ? Html::tag('strong', '&#9658;&nbsp;' . $meta['tag']) : $meta['tag'])
        . ': ' . Html::encode($meta['method']) . ' ' . Html::encode($meta['url']) . ($meta['ajax'] ? ' (AJAX)' : '')
        . ', ' . date('Y-m-d h:i:s a', (int) $meta['time'])
        . ', ' . $meta['ip'];
    $historyItems[] = [
        'label' => $label,
        'url' => ['view', 'tag' => $meta['tag'], 'panel' => $activePanel->id],
    ];
    if (++$count >= 10) {
        break;
    }
}
?>
<div class="yii-debug-page default-view">
    <header class="yii-debug-brand-bar">
        <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="<?= Url::to(['index']) ?>">
            <span class="yii-debug-brand-icon"><?= $inlineSvg('yii.svg') ?></span>
            <span class="yii-debug-brand-label">Yii</span>
            <span class="yii-debug-brand-value"><?= Html::encode($yiiVersion) ?></span>
        </a>
        <div class="yii-debug-brand-chip yii-debug-brand-chip-php">
            <span class="yii-debug-brand-icon"><?= $inlineSvg('php-alt.svg') ?></span>
            <span class="yii-debug-brand-label">PHP</span>
            <span class="yii-debug-brand-value"><?= Html::encode($phpVersion) ?></span>
        </div>
        <?php if ($peakMemory !== null): ?>
            <div class="yii-debug-brand-chip yii-debug-brand-chip-mem">
                <span class="yii-debug-brand-label">Memory</span>
                <span class="yii-debug-brand-value"><?= Html::encode($peakMemory) ?></span>
            </div>
        <?php endif; ?>
    </header>

    <div class="yii-debug-layout">
        <aside class="yii-debug-sidebar">
            <?php if ($activePanel->hasRequestNavigation()): ?>
                <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Snapshot history">
                    <header class="yii-debug-side-section-title">History</header>

                    <div class="yii-debug-snapshot" title="<?= Html::encode(($summary['method'] ?? '') . ' ' . ($summary['url'] ?? '')) ?>">
                        <div class="yii-debug-snapshot-line">
                            <span class="yii-debug-snapshot-method"><?= Html::encode($summary['method'] ?? '') ?></span>
                            <span class="yii-debug-snapshot-url"><?= Html::encode($summary['url'] ?? '') ?></span>
                        </div>
                        <div class="yii-debug-snapshot-meta">
                            <span class="yii-debug-snapshot-status yii-debug-snapshot-status-<?= $statusVariant ?>"><?= $currentStatus ?: '–' ?></span>
                            <?php if (!empty($summary['time'])): ?>
                                <span class="yii-debug-snapshot-time"><?= date('H:i:s', (int) $summary['time']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($summary['ajax'])): ?>
                                <span class="yii-debug-snapshot-tag">AJAX</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="yii-debug-request-nav-row" role="group">
                        <?= NavigationButton::widget(
                            ['manifest' => $manifest, 'tag' => $tag, 'panel' => $activePanel, 'button' => 'Prev'],
                        ) ?>
                        <?= NavigationButton::widget(
                            ['manifest' => $manifest, 'tag' => $tag, 'panel' => $activePanel, 'button' => 'Next'],
                        ) ?>
                    </div>
                    <?= Html::a('Latest', ['view', 'panel' => $activePanel->id], [
                        'class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm yii-debug-request-nav-action',
                    ]) ?>
                    <div class="yii-debug-dropdown yii-debug-request-nav-action">
                        <?= Html::button('Last 10 ▾', [
                            'type' => 'button',
                            'class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm',
                            'data-yii-debug-toggle' => 'dropdown',
                            'aria-haspopup' => 'true',
                            'aria-expanded' => 'false',
                        ]) ?>
                        <?= \yii\widgets\Menu::widget([
                            'encodeLabels' => false,
                            'items' => $historyItems,
                            'options' => ['class' => 'yii-debug-dropdown-menu'],
                            'itemOptions' => ['tag' => 'li'],
                            'linkTemplate' => '<a href="{url}" class="yii-debug-dropdown-item">{label}</a>',
                        ]) ?>
                    </div>
                    <?= Html::a('Browse history…', ['index'], [
                        'class' => 'yii-debug-side-section-link',
                    ]) ?>
                </section>
            <?php endif; ?>

            <nav class="yii-debug-nav" aria-label="Debug panels">
                <?php foreach ($panels as $id => $panel): ?>
                    <?php
                    $isActive = $panel === $activePanel;
                    $linkOptions = ['class' => $isActive ? 'yii-debug-nav-link is-active' : 'yii-debug-nav-link'];
                    if ($isActive) {
                        $linkOptions['aria-current'] = 'page';
                    }
                    echo Html::a(Html::encode($panel->getName()), ['view', 'tag' => $tag, 'panel' => $id], $linkOptions);
                    ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="yii-debug-main yii-debug-card">
            <?= $activePanel->getDetail() ?>
        </main>
    </div>
</div>
