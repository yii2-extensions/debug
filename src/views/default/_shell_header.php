<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Encode;
use yii\debug\helpers\Icon;
use yii\helpers\Url;
use yii\web\View;

/**
 * @var string|null $configUrl URL to the Configuration panel for the active (or latest) request, or `null` when the
 * manifest is empty the chip then renders disabled with a hint.
 * @var string $debugTheme Resolved theme key, `'light'` or `'dark'`.
 * @var string|null $peakMemory Optional formatted peak-memory chip (for example, `1.21 MB`); pass `null` to omit the
 * chip index.php does this because there's no active request.
 * @var string $phpVersion Friendly PHP version, for example, `8.5.3`.
 * @var string $themeIconMoon Pre-loaded moon glyph.
 * @var string $themeIconSun Pre-loaded sun glyph from the controller.
 * @var string $yiiVersion Friendly framework version, for example, `22.0.x-dev`.
 * @var View $this
 */

$themeChipIcon = $debugTheme === 'dark' ? $themeIconSun : $themeIconMoon;
$configIcon = Icon::render('config');
?>
<header class="yii-debug-brand-bar">
    <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="<?= Url::to(['index']) ?>">
        <span class="yii-debug-brand-icon"><?= Icon::render('yii') ?></span>
        <span class="yii-debug-brand-label">Yii</span>
        <span class="yii-debug-brand-value"><?= Encode::content($yiiVersion) ?></span>
    </a>
    <div class="yii-debug-brand-chip yii-debug-brand-chip-php">
        <span class="yii-debug-brand-icon"><?= Icon::render('php-alt') ?></span>
        <span class="yii-debug-brand-value"><?= Encode::content($phpVersion) ?></span>
    </div>
    <?php if ($peakMemory !== null): ?>
        <div class="yii-debug-brand-chip yii-debug-brand-chip-mem">
            <span class="yii-debug-brand-label">Memory</span>
            <span class="yii-debug-brand-value"><?= Encode::content($peakMemory) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($configUrl !== null): ?>
        <a class="yii-debug-brand-chip yii-debug-brand-chip-config"
            href="<?= Encode::value($configUrl) ?>"
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
        data-current-theme="<?= Encode::value($debugTheme) ?>"
        data-icon-sun="<?= Encode::value($themeIconSun) ?>"
        data-icon-moon="<?= Encode::value($themeIconMoon) ?>"
        aria-label="Toggle debug panel theme"
        title="Toggle debug panel theme"
    >
        <span class="yii-debug-brand-icon" aria-hidden="true"><?= $themeChipIcon ?></span>
    </button>
</header>
