<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\{Attributes, Encode};
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\SidebarRenderer;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var string $content Rendered view content for the layout body.
 * @var View $this View component instance.
 */
yii\debug\DebugAsset::register($this);

$shellContext = $this->params['debugShell'] ?? null;

if (!$shellContext instanceof ShellContext) {
    throw new LogicException('The debug layout requires a ShellContext.');
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
            <?= $shellContext->sidebar !== null ? SidebarRenderer::render($shellContext->sidebar) : '' ?>

            <main class="yii-debug-main yii-debug-card">
                <?= $content ?>
            </main>
        </div>
    </div>
<?php else: ?>
    <main class="yii-debug-main-bare">
        <?= $content ?>
    </main>
<?php endif; ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
