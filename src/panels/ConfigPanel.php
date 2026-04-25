<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\debug\Panel;
use yii\debug\VersionResolver;

/**
 * Debugger panel that collects and displays application configuration and environment.
 *
 * @property array $extensions
 * @property array $phpInfo
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ConfigPanel extends Panel
{
    public function getDetail()
    {
        return Yii::$app->view->render('panels/config/detail', ['panel' => $this]);
    }

    /**
     * Returns data about extensions
     *
     * @return array
     */
    public function getExtensions()
    {
        $data = [];
        foreach ($this->data['extensions'] as $extension) {
            $data[$extension['name']] = $extension['version'];
        }
        ksort($data);

        return $data;
    }

    public function getName()
    {
        return 'Configuration';
    }

    /**
     * Returns the BODY contents of the phpinfo() output
     *
     * @return array
     */
    public function getPhpInfo()
    {
        ob_start();
        phpinfo();
        $pinfo = ob_get_contents();
        ob_end_clean();
        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo);
        $phpinfo = str_replace(
            '<table',
            '<div class="yii-debug-table-wrap"><table class="yii-debug-table yii-debug-phpinfo__table" ',
            $phpinfo,
        );
        $phpinfo = str_replace('</table>', '</table></div>', $phpinfo);
        return str_replace('<div class="center">', '<div class="yii-debug-phpinfo">', $phpinfo);
    }

    public function getSummary()
    {
        return Yii::$app->view->render('panels/config/summary', ['panel' => $this]);
    }

    public function save()
    {
        $yiiVersion = VersionResolver::yii();

        return [
            'phpVersion' => PHP_VERSION,
            'yiiVersion' => $yiiVersion,
            'application' => [
                'yii' => $yiiVersion,
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
            'extensions' => VersionResolver::forExtensions(Yii::$app->extensions ?? []),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Configuration is surfaced on the toolbar via the Yii logo/version brand chip (the brand click
     * target is this panel's URL) and a dedicated PHP chip linking to `php-info`. A separate
     * "Configuration" panel chip would duplicate that information, so we suppress it.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems()
    {
        return null;
    }
}
