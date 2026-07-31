<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle for the Timeline panel detail view.
 *
 * Ships the horizontal span chart styling for the server-rendered timeline markup.
 */
class TimelineAsset extends AssetBundle
{
    /**
     * CSS files registered with this bundle.
     */
    public $css = [
        'dist/css/timeline.min.css',
    ];
    /**
     * Asset bundles this bundle depends on.
     */
    public $depends = [
        DebugAsset::class,
    ];
    /**
     * Source path (Yii alias) under which the bundled assets live; published by the Asset Manager on first
     * registration.
     */
    public $sourcePath = '@yii/debug/assets';
}
