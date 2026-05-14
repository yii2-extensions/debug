<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle for the debugger pages: the main CSS theme, the toolbar styles, the panel interactivity, the dark/light
 * theme toggle, and the history-page cursor handling.
 *
 * Registered by the debugger layout (`views/layouts/main.php`) so every full-page debugger view inherits the same
 * styles and behaviors. The toolbar injected on the host application's pages does NOT use this bundle; it ships its own
 * self-contained inline script (see {@see Module::renderToolbar()}).
 */
class DebugAsset extends AssetBundle
{
    /**
     * CSS files registered with this bundle.
     */
    public $css = [
        'dist/css/main.min.css',
        'dist/css/toolbar.min.css',
    ];
    /**
     * JavaScript files registered with this bundle.
     */
    public $js = [
        'dist/js/debug.min.js',
        'dist/js/theme-toggle.min.js',
        'dist/js/history-cursor.min.js',
    ];
    /**
     * Source path (Yii alias) under which the bundled assets live; published by the Asset Manager on first
     * registration.
     */
    public $sourcePath = '@yii/debug/assets';
}
