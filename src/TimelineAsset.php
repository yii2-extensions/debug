<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Timeline asset bundle
 */
class TimelineAsset extends AssetBundle
{
    /**
     * {@inheritdoc}
     */
    public $sourcePath = '@yii/debug/assets';
    /**
     * {@inheritdoc}
     */
    public $css = [
        'css/timeline.css',
    ];
    /**
     * {@inheritdoc}
     */
    public $js = [
        'js/timeline.js',
    ];
    /**
     * {@inheritdoc}
     */
    public $depends = [
        DebugAsset::class,
    ];
}
