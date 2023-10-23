<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * User switch asset bundle
 *
 * @author Semen Dubina <yii2debug@sam002.net>
 *
 * @since 2.0.10
 */
class UserswitchAsset extends AssetBundle
{
    public $sourcePath = '@yii/debug/assets';

    public $js = [
        'js/userswitch.js',
    ];
}
