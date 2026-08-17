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
    public $js = [
        'dist/js/toolbar.min.js',
    ];
    public $jsOptions = [
        'type' => 'module',
    ];
    public $sourcePath = Module::SOURCE_PATH;
}
