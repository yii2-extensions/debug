<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Debugger asset bundle
 */
class DebugAsset extends AssetBundle
{
    /**
     * {@inheritdoc}
     */
    public $sourcePath = '@yii/debug/assets';
    /**
     * {@inheritdoc}
     */
    public $css = [
        'css/main.css',
        'css/toolbar.css',
    ];
    /**
     * {@inheritdoc}
     */
    public $js = [
        'js/polyfill.min.js',
        'js/bs4-native.min.js',
    ];
}
