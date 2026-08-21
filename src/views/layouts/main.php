<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\{Attributes, Encode};
use yii\debug\Module;
use PHPForge\Debug\Helper\Icon;
use yii\debug\exception\Message;
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\SidebarRenderer;
use yii\helpers\{Html, Url};
use yii\web\View;

/**
 * @var string $content Rendered view content for the layout body.
 * @var View $this View component instance.
 */
yii\debug\DebugAsset::register($this);

$shellContext = $this->params['debugShell'] ?? null;

if (!$shellContext instanceof ShellContext) {
    throw new LogicException(Message::SHELL_CONTEXT_REQUIRED->getMessage());
}
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

    <?php $this->head() ?>
</head>
<body class="yii-debug">
<?php $this->beginBody() ?>
<?= $this->renderFile(
    Module::VIEW_PATH_ALIAS . '/_shell.php',
    [
        'actionIcon' => Icon::render('config'),
        'actionLabel' => 'Config',
        'actionTitle' => $shellContext->configUrl === null
            ? 'No requests captured yet'
            : 'Open the Configuration panel',
        'actionUrl' => $shellContext->configUrl,
        'content' => $content,
        'debugTheme' => $shellContext->resolvedTheme,
        'historyUrl' => Url::to(Module::route('index')),
        'mode' => $shellContext->mode,
        'peakMemory' => $shellContext->peakMemory,
        'phpIcon' => Icon::render('php-alt'),
        'phpVersion' => $shellContext->phpVersion,
        'sidebar' => $shellContext->sidebar !== null ? SidebarRenderer::render($shellContext->sidebar) : '',
        'themeIconMoon' => $shellContext->themeIconMoon,
        'themeIconSun' => $shellContext->themeIconSun,
        'useShell' => $shellContext->useShell,
        'yiiIcon' => Icon::render('yii'),
        'yiiVersion' => $shellContext->yiiVersion,
    ],
) ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
