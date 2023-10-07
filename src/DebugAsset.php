<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Debugger asset bundle
 */
class DebugAsset extends AssetBundle
{
    public $sourcePath = '@yii/debug/assets';

    public $css = [
        'css/main.css',
        'css/toolbar.css',
    ];

    public $js = [
        'js/polyfill.min.js',
        'js/bs4-native.min.js',
    ];
}
