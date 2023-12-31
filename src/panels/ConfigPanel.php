<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\debug\Panel;

use function ksort;
use function ob_get_clean;
use function ob_start;
use function phpinfo;
use function preg_replace;
use function str_replace;

/**
 * Debugger panel that collects and displays application configuration and environment.
 *
 * @property array $extensions
 * @property array $phpInfo
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 *
 * @since 2.0
 */
class ConfigPanel extends Panel
{
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/config/detail', ['panel' => $this]);
    }

    /**
     * Returns data about extensions.
     */
    public function getExtensions(): array
    {
        $data = [];

        foreach ($this->data['extensions'] as $extension) {
            $data[$extension['name']] = $extension['version'];
        }

        ksort($data);

        return $data;
    }

    public function getName(): string
    {
        return 'Configuration';
    }

    /**
     * Returns the BODY contents of the phpinfo() output.
     */
    public function getPhpInfo(): string
    {
        ob_start();
        phpinfo();

        $pinfo = ob_get_clean();
        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo);

        return str_replace(
            [
                '<table',
                '</table>',
                '<div class="center">',
            ],
            [
                '<div class="table-responsive"><table class="table table-condensed table-bordered table-striped table-hover config-php-info-table" ',
                '</table></div>',
                '<div class="phpinfo">',
            ],
            $phpinfo,
        );
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/config/summary', ['panel' => $this]);
    }

    public function save(): mixed
    {
        return [
            'phpVersion' => PHP_VERSION,
            'yiiVersion' => Yii::getVersion(),
            'application' => [
                'yii' => Yii::getVersion(),
                'name' => Yii::$app->name,
                'version' => Yii::$app->version,
                'language' => Yii::$app->language,
                'sourceLanguage' => Yii::$app->sourceLanguage,
                'charset' => Yii::$app->charset,
                'env' => YII_ENV,
                'debug' => YII_DEBUG,
            ],
            'php' => [
                'version' => PHP_VERSION,
                'xdebug' => extension_loaded('xdebug'),
                'apc' => extension_loaded('apc'),
                'memcache' => extension_loaded('memcache'),
                'memcached' => extension_loaded('memcached'),
            ],
            'extensions' => Yii::$app->extensions,
        ];
    }
}
