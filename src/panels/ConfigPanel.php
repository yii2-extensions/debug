<?php

declare(strict_types=1);

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
 */
class ConfigPanel extends Panel
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Configuration';
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/config/summary', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/config/detail', ['panel' => $this]);
    }

    /**
     * Returns data about extensions
     *
     * @return array
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

    /**
     * Returns the BODY contents of the phpinfo() output
     *
     * @return array
     */
    public function getPhpInfo(): array
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

    /**
     * {@inheritdoc}
     */
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
