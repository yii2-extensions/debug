<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle powering the EXPLAIN toggle on the Database panel queries grid.
 *
 * Ships the inline-AJAX handler that fetches the EXPLAIN plan for a query when its `Explain` button is clicked and
 * renders the result inline without leaving the grid.
 */
class DbAsset extends AssetBundle
{
    /**
     * JavaScript files registered with this bundle.
     */
    public $js = [
        'dist/js/db.min.js',
    ];
    /**
     * Source path (Yii alias) under which the bundled assets live; published by the Asset Manager on first
     * registration.
     */
    public $sourcePath = '@yii/debug/assets';
}
