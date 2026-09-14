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
    /**
     * Stylesheet of the debugger pages.
     */
    public $css = [
        'dist/css/debug.min.css',
    ];
    /**
     * Scripts driving panel interactivity, the theme toggle, and the history cursor.
     */
    public $js = [
        'dist/js/debug.min.js',
    ];
    /**
     * Loads the bundle scripts as ES modules, keeping their top-level identifiers module-scoped.
     */
    public $jsOptions = [
        'type' => 'module',
    ];
    /**
     * Published source directory of the packaged debugger assets.
     */
    public $sourcePath = Module::SOURCE_PATH;
}
