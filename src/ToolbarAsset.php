<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Defines the Yii2 asset bundle for the shared toolbar runtime.
 */
final class ToolbarAsset extends AssetBundle
{
    public $js = [
        'dist/js/toolbar.min.js',
    ];
    public $sourcePath = Module::SOURCE_PATH;
}
