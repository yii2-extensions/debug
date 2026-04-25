<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var string $content */

yii\debug\DebugAsset::register($this);

$debugTheme = null;
if (Yii::$app instanceof \yii\web\Application) {
    $request = Yii::$app->getRequest();
    $debugTheme = $request->get('yii_debug_theme', $request->getCookies()->getValue('yii-debug-toolbar-theme'));
}
$debugTheme = strtolower((string) $debugTheme);
$debugThemeAttributes = in_array($debugTheme, ['dark', 'light'], true) ? ['data-yii-debug-theme' => $debugTheme] : [];
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html<?= Html::renderTagAttributes($debugThemeAttributes) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="none"/>
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode(Yii::$app->controller->module->htmlTitle()) ?></title>
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
