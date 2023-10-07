<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * DB asset bundle
 */
class DbAsset extends AssetBundle
{
    public $sourcePath = '@yii/debug/assets';
    
    public $js = [
        'js/db.js',
    ];
}
