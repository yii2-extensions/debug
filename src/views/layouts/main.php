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
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
