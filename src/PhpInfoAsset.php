<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle powering the keyword filter on the PhpInfo panel detail view.
 *
 * Ships the inline search handler that filters the `phpinfo()` table rows in place as the user types, so the panel
 * stays responsive even on configurations with hundreds of entries.
 */
class PhpInfoAsset extends AssetBundle
{
    /**
     * Asset bundles this bundle depends on.
     */
    public $depends = [
        DebugAsset::class,
    ];
    /**
     * JavaScript files registered with this bundle.
     */
    public $js = [
        'dist/js/phpinfo-search.min.js',
    ];
    /**
     * Source path (Yii alias) under which the bundled assets live; published by the Asset Manager on first
     * registration.
     */
    public $sourcePath = '@yii/debug/assets';
}
