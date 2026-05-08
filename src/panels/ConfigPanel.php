<?php

declare(strict_types=1);

namespace yii\debug\panels;

use ReflectionClass;
use Yii;
use yii\base\Application;
use yii\debug\{Panel, VersionResolver};
use yii\debug\panels\config\ConfigDataNormalizer;

use function is_array;
use function is_object;
use function is_scalar;
use function is_string;

/**
 * Debugger panel that collects and displays application configuration and environment.
 */
class ConfigPanel extends Panel
{
    public function getDetail(): string
    {
        $summary = (new ConfigDataNormalizer())->normalize($this->data, $this->getExtensions());

        return Yii::$app->view->render('panels/config/detail', ['summary' => $summary]);
    }

    /**
     * @return array<string, string>
     */
    public function getExtensions(): array
    {
        $data = [];

        $panelData = is_array($this->data) ? $this->data : [];
        $extensions = is_array($panelData['extensions'] ?? null) ? $panelData['extensions'] : [];

        foreach ($extensions as $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $name = $extension['name'] ?? null;
            $version = $extension['version'] ?? null;

            if (is_scalar($name) && is_scalar($version)) {
                $data[(string) $name] = (string) $version;
            }
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

        if (!is_string($pinfo)) {
            return '';
        }

        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo);

        if (!is_string($phpinfo)) {
            $phpinfo = $pinfo;
        }

        $phpinfo = str_replace(
            '<table',
            '<div class="yii-debug-table-wrap"><table class="yii-debug-table yii-debug-phpinfo__table" ',
            $phpinfo,
        );
        $phpinfo = str_replace(
            '</table>',
            '</table></div>',
            $phpinfo,
        );

        return str_replace(
            '<div class="center">',
            '<div class="yii-debug-phpinfo">',
            $phpinfo,
        );
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/config/summary', ['panel' => $this]);
    }

    public function getToolbarIcon(): string
    {
        return 'config';
    }

    /**
     * @return array{
     *     phpVersion: string,
     *     yiiVersion: string,
     *     application: array{
     *         yii: string,
     *         name: string,
     *         version: string,
     *         language: string,
     *         sourceLanguage: string,
     *         charset: string,
     *         env: string,
     *         debug: bool
     *     },
     *     php: array{
     *         version: string,
     *         xdebug: bool,
     *         apcu: bool,
     *         memcache: bool,
     *         memcached: bool
     *     },
     *     extensions: array<int|string, array<string, mixed>>
     * }
     */
    public function save(): array
    {
        $app = $this->getApplication();

        $yiiVersion = VersionResolver::yii();

        $application = [
            'yii' => $yiiVersion,
            'name' => '',
            'version' => '',
            'language' => '',
            'sourceLanguage' => '',
            'charset' => '',
            'env' => YII_ENV,
            'debug' => YII_DEBUG,
        ];

        $extensions = [];

        if ($app instanceof Application) {
            $application['name'] = $app->name;
            $application['version'] = $app->version;
            $application['language'] = $app->language;
            $application['sourceLanguage'] = $app->sourceLanguage;
            $application['charset'] = $app->charset;
            $extensions = is_array($app->extensions) ? $app->extensions : [];
        }

        return [
            'phpVersion' => PHP_VERSION,
            'yiiVersion' => $yiiVersion,
            'application' => $application,
            'php' => [
                'version' => PHP_VERSION,
                'xdebug' => extension_loaded('xdebug'),
                'apcu' => extension_loaded('apcu'),
                'memcache' => extension_loaded('memcache'),
                'memcached' => extension_loaded('memcached'),
            ],
            'extensions' => VersionResolver::forExtensions(self::normalizeExtensions($extensions)),
        ];
    }

    protected function getApplication(): object|null
    {
        $app = (new ReflectionClass(Yii::class))->getStaticPropertyValue('app');

        return is_object($app) ? $app : null;
    }

    /**
     * Configuration is surfaced on the toolbar via the Yii logo/version brand chip (the brand click target is this
     * panel's URL) and a dedicated PHP chip linking to `php-info`. A separate "Configuration" panel chip would
     * duplicate that information, so we suppress it.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems(): array|null
    {
        return null;
    }

    /**
     * @param array<int|string, mixed> $extensions
     *
     * @return array<int|string, array<string, mixed>>
     */
    private static function normalizeExtensions(array $extensions): array
    {
        $normalized = [];

        foreach ($extensions as $name => $extension) {
            if (is_array($extension)) {
                $normalizedExtension = [];

                foreach ($extension as $key => $value) {
                    if (is_string($key)) {
                        $normalizedExtension[$key] = $value;
                    }
                }

                $normalized[$name] = $normalizedExtension;
            }
        }

        return $normalized;
    }
}
