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
        'css/main.min.css',
        'css/toolbar.min.css',
    ];
    public $js = [
        'js/debug.js',
    ];
    public $sourcePath = '@yii/debug/assets';
}
