<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Defines the Yii2 asset bundle for the shared toolbar runtime.
 *
 * The runtime is an ES-module chunk; it must load with `type="module"` so its minified top-level identifiers stay
 * module-scoped instead of leaking into the shared global scope of classic scripts.
 */
final class ToolbarAsset extends AssetBundle
{
    /**
     * Runtime driving the floating debug toolbar.
     */
    public $js = [
        'dist/js/toolbar.min.js',
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
