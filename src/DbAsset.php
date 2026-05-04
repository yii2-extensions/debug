<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * DB asset bundle.
 */
class DbAsset extends AssetBundle
{
    public $js = [
        'dist/js/db.js',
    ];
    public $sourcePath = '@yii/debug/assets';
}
