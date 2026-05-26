<?php

declare(strict_types=1);

use yii\debug\PhpInfoAsset;
use yii\debug\widgets\phpinfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use yii\web\View;

/**
 * @var View $this View component instance.
 */
PhpInfoAsset::register($this);

$this->title = 'PHP Info';

ob_start();
phpinfo();
$pinfo = ob_get_contents();
ob_end_clean();

$body = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', (string) $pinfo) ?? '';

$view = PhpInfoDataNormalizer::fromOutput(
    body: $body,
    phpVersion: PHP_VERSION,
    sapi: PHP_SAPI,
    os: php_uname('s') . ' ' . php_uname('r'),
    memoryLimit: ini_get('memory_limit'),
);
?>
<div class="yii-debug-page">
    <h1 class="yii-debug-hero-title">phpinfo</h1>
    <?= PhpInfoRenderer::render($view) ?>
</div>
