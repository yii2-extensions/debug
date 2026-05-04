<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * PhpInfo panel asset bundle.
 */
class PhpInfoAsset extends AssetBundle
{
    public $depends = [
        'yii\debug\DebugAsset',
    ];
    public $js = [
        'dist/js/phpinfo-search.js',
    ];
    public $sourcePath = '@yii/debug/assets';
}
