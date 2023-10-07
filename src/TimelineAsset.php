<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Timeline asset bundle.
 */
class TimelineAsset extends AssetBundle
{
    public $sourcePath = '@yii/debug/assets';
    
    public $css = [
        'css/timeline.css',
    ];
    
    public $js = [
        'js/timeline.js',
    ];
    
    public $depends = [
        DebugAsset::class,
    ];
}
