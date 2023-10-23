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
 * DB asset bundle.
 *
 * @author Simon Karlen (simi.albi@outlook.com)
 * @since 2.1.0
 */
class DbAsset extends AssetBundle
{
    public $sourcePath = '@yii/debug/assets';

    public $js = [
        'js/db.js',
    ];
}
