<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// ensure we get report on all possible php errors
error_reporting(-1);

defined('YII_ENABLE_ERROR_HANDLER') || define('YII_ENABLE_ERROR_HANDLER', false);
defined('YII_DEBUG') || define('YII_DEBUG', true);

$_SERVER['SCRIPT_NAME'] = '/' . __DIR__;
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

$rootPath = dirname(__DIR__, 2);

$vendor = "{$rootPath}/vendor";

if (is_dir($vendor)) {
    $vendorRoot = $vendor; //this extension has its own vendor folder
} else {
    $vendorRoot = dirname($rootPath, 1); //this extension is part of a project vendor folder
}

require_once($vendorRoot . '/autoload.php');
require_once($vendorRoot . '/yiisoft/yii2/Yii.php');

Yii::setAlias('@yii/debug/tests', dirname(__DIR__));
Yii::setAlias('@yii/debug', "{$rootPath}/src");
