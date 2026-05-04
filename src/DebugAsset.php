<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Debugger asset bundle.
 */
class DebugAsset extends AssetBundle
{
    public $css = [
        'dist/css/main.min.css',
        'dist/css/toolbar.min.css',
    ];
    public $js = [
        'dist/js/debug.js',
        'dist/js/theme-toggle.js',
        'dist/js/history-cursor.js',
    ];
    public $sourcePath = '@yii/debug/assets';
}
