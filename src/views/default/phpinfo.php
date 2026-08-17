<?php

declare(strict_types=1);

use PHPForge\Debug\PhpInfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use yii\web\View;

/**
 * @var View $this View component instance.
 */
$this->title = 'PHP Info';
?>
<div class="yii-debug-page">
    <h1 class="yii-debug-hero-title">phpinfo</h1>
    <?= PhpInfoRenderer::render(PhpInfoDataNormalizer::capture()) ?>
</div>
