<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle powering the interactive chart on the Timeline panel detail view.
 *
 * Ships the horizontal span chart styling plus the hover/zoom handler that maps cursor coordinates to the underlying
 * profile spans, so the user can inspect individual frames without leaving the panel.
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
     * JavaScript files registered with this bundle.
     */
    public $js = [
        'dist/js/timeline.min.js',
    ];
    /**
     * Source path (Yii alias) under which the bundled assets live; published by the Asset Manager on first
     * registration.
     */
    public $sourcePath = '@yii/debug/assets';
}
