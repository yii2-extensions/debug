<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\{Attributes, Encode};
use yii\debug\widgets\shell\ShellDataNormalizer;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var string $content
 * @var View $this
 */

yii\debug\DebugAsset::register($this);

// `debugTheme` is primed by DefaultController::primeThemeContext() and exposed via $this->params; we still fall back to
// the request/cookie pair so direct hits on the layout (legacy/tests) keep working.
$debugTheme = is_string($this->params['debugTheme'] ?? null) ? $this->params['debugTheme'] : '';

if ($debugTheme === '') {
    $debugTheme = ShellDataNormalizer::resolveThemeFromRequest();
}

$controller = Yii::$app->controller;

$shellContext = ShellDataNormalizer::fromParams(
    $this->params['shellMode'] ?? 'bare',
    $this->params['shellData'] ?? null,
    $debugTheme,
    $controller !== null ? $controller->module : null,
);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html<?= Attributes::render($shellContext->debugThemeAttributes) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="none"/>

    <?= Html::csrfMetaTags() ?>
    <title><?= Encode::content($shellContext->title) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= \yii\debug\Module::getYiiLogo() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Major+Mono+Display&display=swap">

    <?php $this->head() ?>
</head>
<body class="yii-debug">
<?php $this->beginBody() ?>
<?php if ($shellContext->useShell): ?>
    <div class="yii-debug-page default-<?= Encode::value($shellContext->mode) ?>">
        <?= $this->render(
            '../default/_shell_header',
            [
                'debugTheme' => $shellContext->resolvedTheme,
                'themeIconSun' => $shellContext->themeIconSun,
                'themeIconMoon' => $shellContext->themeIconMoon,
                'yiiVersion' => $shellContext->yiiVersion,
                'phpVersion' => $shellContext->phpVersion,
                'peakMemory' => $shellContext->peakMemory,
                'configUrl' => $shellContext->configUrl,
            ],
        ) ?>

        <div class="yii-debug-layout">
            <?= $this->render(
                '../default/_sidebar',
                [
                    'mode' => $shellContext->mode,
                    'panels' => $shellContext->shellPanels,
                    'manifest' => $shellContext->shellManifest,
                    'activePanel' => $shellContext->activePanel,
                    'tag' => $shellContext->activeTag,
                    'summary' => $shellContext->shellSummary,
                    'cursorInit' => $shellContext->cursorInit,
                ],
            ) ?>

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
<?php $this->endPage();
