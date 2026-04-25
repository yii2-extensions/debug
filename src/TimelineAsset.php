<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Timeline asset bundle
 */
class TimelineAsset extends AssetBundle
{
    public $css = [
        'css/timeline.min.css',
    ];
    public $depends = [
        'yii\debug\DebugAsset',
    ];
    public $js = [
        'js/timeline.js',
    ];
    public $sourcePath = '@yii/debug/assets';
}
