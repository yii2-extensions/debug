<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * User switch asset bundle.
 */
class UserswitchAsset extends AssetBundle
{
    public $sourcePath = '@yii/debug/assets';

    public $js = [
        'js/userswitch.js',
    ];
}
