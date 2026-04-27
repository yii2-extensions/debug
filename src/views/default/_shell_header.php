<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Shell header partial — the brand bar (Yii / PHP / Memory? / Config / Theme toggle) plus the
 * IIFE that wires the theme chip's click. Shared between view.php and index.php so any change
 * to the top-of-page chrome happens in one file.
 *
 * @var \yii\web\View $this
 * @var Closure(string): string $inlineSvg Closure that resolves an SVG glyph by filename and
 *      returns its inline markup (or '' when missing).
 * @var string $debugTheme Resolved theme key, `'light'` or `'dark'`.
 * @var string $themeIconSun Pre-loaded sun glyph from the controller.
 * @var string $themeIconMoon Pre-loaded moon glyph.
 * @var string $yiiVersion Friendly framework version, e.g. `22.0.x-dev`.
 * @var string $phpVersion Friendly PHP version, e.g. `8.5.3`.
 * @var string|null $peakMemory Optional formatted peak-memory chip (e.g. `1.21 MB`); pass `null`
 *      to omit the chip — index.php does this because there's no active request.
 * @var string|null $configUrl URL to the Configuration panel for the active (or latest) request,
 *      or `null` when the manifest is empty — the chip then renders disabled with a hint.
 */

$themeChipIcon = $debugTheme === 'dark' ? $themeIconSun : $themeIconMoon;
$configIcon = $inlineSvg('config.svg');
?>
<header class="yii-debug-brand-bar">
    <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="<?= Url::to(['index']) ?>">
        <span class="yii-debug-brand-icon"><?= $inlineSvg('yii.svg') ?></span>
        <span class="yii-debug-brand-label">Yii</span>
        <span class="yii-debug-brand-value"><?= Html::encode($yiiVersion) ?></span>
    </a>
    <div class="yii-debug-brand-chip yii-debug-brand-chip-php">
        <span class="yii-debug-brand-icon"><?= $inlineSvg('php-alt.svg') ?></span>
        <span class="yii-debug-brand-value"><?= Html::encode($phpVersion) ?></span>
    </div>
    <?php if ($peakMemory !== null): ?>
        <div class="yii-debug-brand-chip yii-debug-brand-chip-mem">
            <span class="yii-debug-brand-label">Memory</span>
            <span class="yii-debug-brand-value"><?= Html::encode($peakMemory) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($configUrl !== null): ?>
        <a class="yii-debug-brand-chip yii-debug-brand-chip-config"
           href="<?= Html::encode($configUrl) ?>"
           title="Open the Configuration panel"
           aria-label="Open the Configuration panel">
            <span class="yii-debug-brand-icon" aria-hidden="true"><?= $configIcon ?></span>
            <span class="yii-debug-brand-label">Config</span>
        </a>
    <?php else: ?>
        <span class="yii-debug-brand-chip yii-debug-brand-chip-config is-disabled"
              title="No requests captured yet"
              aria-disabled="true">
            <span class="yii-debug-brand-icon" aria-hidden="true"><?= $configIcon ?></span>
            <span class="yii-debug-brand-label">Config</span>
        </span>
    <?php endif; ?>
    <button
        type="button"
        class="yii-debug-brand-chip yii-debug-brand-chip-theme"
        data-yii-debug-theme-toggle
        data-current-theme="<?= Html::encode($debugTheme) ?>"
        data-icon-sun="<?= Html::encode($themeIconSun) ?>"
        data-icon-moon="<?= Html::encode($themeIconMoon) ?>"
        aria-label="Toggle debug panel theme"
        title="Toggle debug panel theme"
    >
        <span class="yii-debug-brand-icon" aria-hidden="true"><?= $themeChipIcon ?></span>
    </button>
</header>
<script>
    (function () {
        var btn = document.querySelector('[data-yii-debug-theme-toggle]');
        if (!btn) {
            return;
        }
        var iconSlot = btn.querySelector('.yii-debug-brand-icon');
        btn.addEventListener('click', function () {
            var html = document.documentElement;
            var current = html.getAttribute('data-yii-debug-theme') || btn.getAttribute('data-current-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-yii-debug-theme', next);
            btn.setAttribute('data-current-theme', next);
            if (iconSlot) {
                iconSlot.innerHTML = next === 'dark' ? btn.getAttribute('data-icon-sun') : btn.getAttribute('data-icon-moon');
            }
            try { localStorage.setItem('yii-debug-toolbar-theme', next); } catch (_e) {}
            document.cookie = 'yii-debug-toolbar-theme=' + next + ';path=/;max-age=31536000;samesite=lax';
            if (window.parent && window.parent !== window) {
                try {
                    window.parent.postMessage({ source: 'yii-debug-toolbar', type: 'theme', theme: next }, '*');
                } catch (_e) {}
            }
        });
    }());
</script>
