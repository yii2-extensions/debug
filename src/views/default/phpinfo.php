<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var \yii\web\View $this */

$this->title = 'PHP Info';

ob_start();
phpinfo();
$pinfo = ob_get_contents();
ob_end_clean();

$body = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', (string) $pinfo) ?? '';
$body = str_replace(
    '<table',
    '<div class="yii-debug-table-wrap"><table class="yii-debug-table yii-debug-phpinfo__table" ',
    $body,
);
$body = str_replace('</table>', '</table></div>', $body);
$body = str_replace('<div class="center">', '<div class="yii-debug-phpinfo">', $body);

$memoryLimit = ini_get('memory_limit');
$metaItems = [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'memory limit' => is_string($memoryLimit) ? $memoryLimit : '',
];
?>
<div class="yii-debug-page">
    <h1 class="yii-debug-hero-title">phpinfo</h1>

    <div class="yii-debug-phpinfo-meta">
        <?php foreach ($metaItems as $key => $value): ?>
            <span class="yii-debug-phpinfo-meta-item">
                <span class="yii-debug-phpinfo-meta-key"><?= Html::encode($key) ?></span>
                <span class="yii-debug-phpinfo-meta-value"><?= Html::encode($value) ?></span>
            </span>
        <?php endforeach; ?>
    </div>

    <?= $body ?>
</div>
