<?php

declare(strict_types=1);

namespace yii\debug;

use yii\web\AssetBundle;

/**
 * Asset bundle powering the impersonation form on the User panel.
 *
 * Ships the inline handler that issues the user-switch request and reloads the toolbar so the new identity takes effect
 * without forcing the developer to navigate away from the page being inspected.
 */
class UserswitchAsset extends AssetBundle
{
    /**
     * JavaScript files registered with this bundle.
     */
    public $js = [
        'dist/js/userswitch.min.js',
    ];
    /**
     * Source path (Yii alias) under which the bundled assets live; published by the Asset Manager on first
     * registration.
     */
    public $sourcePath = '@yii/debug/assets';
}
