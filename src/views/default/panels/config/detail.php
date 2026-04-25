<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\debug\panels\ConfigPanel $panel */

$app = $panel->data['application'];
$php = $panel->data['php'];

$formatLanguage = function ($locale) {
    if ($locale === null || $locale === '') {
        return '';
    }
    if (class_exists('Locale', false)) {
        $language = Locale::getDisplayLanguage($locale, 'en');
        $region = Locale::getDisplayRegion($locale, 'en');
        $suffix = implode(', ', array_filter([$language, $region]));
        if ($suffix !== '') {
            return $locale . ' (' . $suffix . ')';
        }
    }
    return $locale;
};

$phpExtensions = [
    'Xdebug' => !empty($php['xdebug']),
    'APC' => !empty($php['apc']),
    'Memcache' => !empty($php['memcache']),
    'Memcached' => !empty($php['memcached']),
];

$extensions = $panel->getExtensions();

$phpInfoUrl = Url::to(['php-info']);

$corners = ['tl', 'tr', 'bl', 'br'];
$renderCorners = function () use ($corners) {
    $out = '';
    foreach ($corners as $c) {
        $out .= '<span class="yii-debug-readout-corner" data-corner="' . $c . '" aria-hidden="true"></span>';
    }
    return $out;
};
?>
<h1 class="yii-debug-hero-title">configuration</h1>

<div class="yii-debug-readout">
    <article class="yii-debug-readout-card">
        <?= $renderCorners() ?>
        <span class="yii-debug-readout-label">Yii</span>
        <span class="yii-debug-readout-value"><?= Html::encode($app['yii']) ?></span>
        <span class="yii-debug-readout-meta">framework</span>
    </article>
    <article class="yii-debug-readout-card">
        <?= $renderCorners() ?>
        <span class="yii-debug-readout-label">PHP</span>
        <span class="yii-debug-readout-value"><?= Html::encode($php['version']) ?></span>
        <span class="yii-debug-readout-meta">runtime</span>
    </article>
    <article class="yii-debug-readout-card">
        <?= $renderCorners() ?>
        <span class="yii-debug-readout-label">Environment</span>
        <span class="yii-debug-readout-value"><?= Html::encode($app['env']) ?></span>
        <span class="yii-debug-readout-meta">
            <?php if ($app['debug']): ?>
                <span class="yii-debug-readout-chip">debug&nbsp;on</span>
            <?php else: ?>
                <span class="yii-debug-readout-chip yii-debug-readout-chip-muted">debug&nbsp;off</span>
            <?php endif; ?>
        </span>
    </article>
    <article class="yii-debug-readout-card">
        <?= $renderCorners() ?>
        <span class="yii-debug-readout-label">Application</span>
        <span class="yii-debug-readout-value"><?= Html::encode($app['name'] ?: '—') ?></span>
        <span class="yii-debug-readout-meta">
            <?php if (!empty($app['version'])): ?>
                <span class="yii-debug-readout-chip yii-debug-readout-chip-muted">v<?= Html::encode($app['version']) ?></span>
            <?php else: ?>
                instance
            <?php endif; ?>
        </span>
    </article>
</div>

<section class="yii-debug-section">
    <h2 class="yii-debug-section-title">
        <span class="yii-debug-section-mark">::</span> PHP extensions
    </h2>
    <div class="yii-debug-ext-strip">
        <?php foreach ($phpExtensions as $name => $enabled): ?>
            <span class="yii-debug-ext-pill <?= $enabled ? 'is-on' : 'is-off' ?>">
                <span class="yii-debug-ext-pill-dot" aria-hidden="true"></span>
                <span class="yii-debug-ext-pill-label"><?= Html::encode($name) ?></span>
                <span class="yii-debug-ext-pill-state"><?= $enabled ? 'on' : 'off' ?></span>
            </span>
        <?php endforeach; ?>
    </div>
</section>

<section class="yii-debug-section">
    <h2 class="yii-debug-section-title">
        <span class="yii-debug-section-mark">//</span> Application details
    </h2>
    <dl class="yii-debug-dl">
        <div class="yii-debug-dl-row">
            <dt>Charset</dt>
            <dd><?= Html::encode($app['charset'] ?: '—') ?></dd>
        </div>
        <div class="yii-debug-dl-row">
            <dt>Current language</dt>
            <dd><?= Html::encode($formatLanguage($app['language']) ?: '—') ?></dd>
        </div>
        <div class="yii-debug-dl-row">
            <dt>Source language</dt>
            <dd><?= Html::encode($formatLanguage($app['sourceLanguage']) ?: '—') ?></dd>
        </div>
    </dl>
</section>

<?php if (!empty($extensions)): ?>
    <section class="yii-debug-section">
        <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">&gt;_</span> Installed extensions
            <span class="yii-debug-section-count"><?= count($extensions) ?></span>
        </h2>
        <div class="yii-debug-packages">
            <?php foreach ($extensions as $name => $version): ?>
                <article class="yii-debug-package">
                    <span class="yii-debug-package-glyph" aria-hidden="true">◆</span>
                    <span class="yii-debug-package-name"><?= Html::encode($name) ?></span>
                    <span class="yii-debug-package-version">v<?= Html::encode($version) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<a class="yii-debug-cta" href="<?= $phpInfoUrl ?>" target="_blank" rel="noopener">
    <span class="yii-debug-cta-prompt" aria-hidden="true">→</span>
    <span>View full phpinfo</span>
    <span class="yii-debug-cta-external" aria-hidden="true">↗</span>
</a>
