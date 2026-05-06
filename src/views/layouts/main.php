<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var string $content */

yii\debug\DebugAsset::register($this);

// `debugTheme` is primed by DefaultController::primeThemeContext() and exposed via $this->params; we still
// fall back to the request/cookie pair so direct hits on the layout (legacy/tests) keep working.
$debugTheme = is_string($this->params['debugTheme'] ?? null) ? $this->params['debugTheme'] : '';

if ($debugTheme === '') {
    $request = Yii::$app->getRequest();
    $rawTheme = $request->get('yii_debug_theme', $request->getCookies()->getValue('yii-debug-toolbar-theme'));
    $debugTheme = is_string($rawTheme) ? strtolower($rawTheme) : '';
}

$debugThemeAttributes = in_array($debugTheme, ['dark', 'light'], true) ? ['data-yii-debug-theme' => $debugTheme] : [];

$controller = Yii::$app->controller;
$module = $controller?->module;
$title = $module instanceof \yii\debug\Module ? $module->htmlTitle() : 'Yii Debugger';

// -------------------------------------------------------------------
// Shell composition
//
// The page-level chrome (brand bar + sidebar + main wrapper) lives here
// in the layout instead of being repeated in every view. Each action
// declares two view params before rendering:
//   - `shellMode` ∈ {'view', 'index', 'bare'} — picks the shell layout.
//   - `shellData` array — payload the shell partials need (panels,
//     manifest, summary, active panel, theme icons, etc).
// `'bare'` (or no shellMode set) skips the shell entirely; the view's
// content renders raw inside the body. Used by phpinfo and db-explain.
// -------------------------------------------------------------------
$shellMode = $this->params['shellMode'] ?? 'bare';
$shellData = (array) ($this->params['shellData'] ?? []);
$useShell = in_array($shellMode, ['view', 'index'], true);

if ($useShell) {
    $shellPanels = is_array($shellData['panels'] ?? null) ? $shellData['panels'] : [];
    $configData = isset($shellPanels['config']) ? ($shellPanels['config']->data ?? []) : [];
    $yiiVersion = (string) ($configData['application']['yii'] ?? Yii::getVersion());
    $phpVersion = (string) ($configData['php']['version'] ?? PHP_VERSION);

    $shellSummary = is_array($shellData['summary'] ?? null) ? $shellData['summary'] : null;
    $peakMemory = $shellSummary !== null && isset($shellSummary['peakMemory'])
        ? sprintf('%.2f MB', $shellSummary['peakMemory'] / 1024 / 1024)
        : null;

    $themeIconSun = (string) ($shellData['themeIconSun'] ?? '');
    $themeIconMoon = (string) ($shellData['themeIconMoon'] ?? '');
    $resolvedTheme = (string) ($shellData['debugTheme'] ?? $debugTheme);
    if ($resolvedTheme === '') {
        $resolvedTheme = 'light';
    }

    // Resolve a target tag for the Configuration chip in the brand bar:
    // current tag when we're inside a view, otherwise latest captured tag.
    // When the manifest is empty we leave it null so the chip renders
    // disabled.
    $manifestForShell = is_array($shellData['manifest'] ?? null) ? $shellData['manifest'] : [];
    $configTargetTag = $shellData['tag'] ?? null;
    if (!is_string($configTargetTag) || $configTargetTag === '') {
        $configTargetTag = $manifestForShell === [] ? null : array_key_first($manifestForShell);
    }
    $configUrl = $configTargetTag === null
        ? null
        : \yii\helpers\Url::to(['/' . $module->getUniqueId() . '/default/view', 'panel' => 'config', 'tag' => $configTargetTag]);
}
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html<?= Html::renderTagAttributes($debugThemeAttributes) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="none"/>
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($title) ?></title>
    <link rel="icon" type="image/png" href="<?= \yii\debug\Module::getYiiLogo() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Major+Mono+Display&display=swap">
    <?php $this->head() ?>
</head>
<body class="yii-debug">
<?php $this->beginBody() ?>
<?php if ($useShell): ?>
    <div class="yii-debug-page default-<?= Html::encode($shellMode) ?>">
        <?= $this->render('../default/_shell_header', [
            'debugTheme' => $resolvedTheme,
            'themeIconSun' => $themeIconSun,
            'themeIconMoon' => $themeIconMoon,
            'yiiVersion' => $yiiVersion,
            'phpVersion' => $phpVersion,
            'peakMemory' => $peakMemory,
            'configUrl' => $configUrl,
        ]) ?>

        <div class="yii-debug-layout">
            <?= $this->render('../default/_sidebar', [
                'mode' => $shellMode,
                'panels' => $shellPanels,
                'manifest' => is_array($shellData['manifest'] ?? null) ? $shellData['manifest'] : [],
                'activePanel' => $shellData['activePanel'] ?? null,
                'tag' => $shellData['tag'] ?? null,
                'summary' => $shellSummary,
                'cursorInit' => is_string($shellData['cursorInit'] ?? null) ? $shellData['cursorInit'] : '',
            ]) ?>

            <main class="yii-debug-main yii-debug-card">
                <?= $content ?>
            </main>
        </div>
    </div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
