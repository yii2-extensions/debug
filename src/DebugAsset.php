<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Debugger asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
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
