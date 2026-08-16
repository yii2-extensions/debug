<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle for the debugger pages: the main CSS theme, the panel interactivity, the dark/light theme toggle, and
 * the history-page cursor handling.
 */
class DebugAsset extends AssetBundle
{
    public $css = [
        'dist/css/debug.min.css',
    ];
    public $js = [
        'dist/js/debug.min.js',
    ];
    public $sourcePath = Module::SOURCE_PATH;
}
