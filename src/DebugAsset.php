<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle for the debugger pages: the main CSS theme, the panel interactivity, the dark/light theme toggle, and
 * the history-page cursor handling.
 *
 * The panel script is an ES-module chunk; it must load with `type="module"` so its minified top-level identifiers
 * stay module-scoped instead of leaking into the shared global scope of classic scripts.
 */
class DebugAsset extends AssetBundle
{
    public $css = [
        'dist/css/debug.min.css',
    ];
    public $js = [
        'dist/js/debug.min.js',
    ];
    public $jsOptions = [
        'type' => 'module',
    ];
    public $sourcePath = Module::SOURCE_PATH;
}
