<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * User switch asset bundle.
 */
class UserswitchAsset extends AssetBundle
{
    /**
     * {@inheritdoc}
     */
    public $sourcePath = '@yii/debug/assets';
    /**
     * {@inheritdoc}
     */
    public $js = [
        'js/userswitch.js',
    ];
}
