<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\PhpInfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use UIAwesome\Html\Heading\H1;
use yii\web\View;

/**
 * @var View $this View component instance.
 */
$this->title = PanelTitle::PHP_INFO->value;
?>
<div class="yii-debug-page">
    <?= H1::tag()->class('yii-debug-hero-title')->content(PanelTitle::PHPINFO) ?>
    <?= PhpInfoRenderer::render(PhpInfoDataNormalizer::capture()) ?>
</div>
