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
 * Captures the application configuration and runtime environment shown in the Configuration panel.
 *
 * Stores the Yii framework / PHP / application identity and the installed-extensions roster, then surfaces it through
 * the detail view, the toolbar's `php-info` link, and the brand-chip version readouts.
 */
class ConfigPanel extends Panel
{
    /**
     * Renders the detail view from the normalized configuration summary.
     */
    public function getDetail(): string
    {
        $summary = (new ConfigDataNormalizer())->normalize($this->data, $this->getExtensions());

        return Yii::$app->view->render(
            'panels/config/detail',
            ['summary' => $summary],
        );
    }

    /**
     * Returns the installed-extensions roster as a sorted `name => version` map.
     *
     * @return array<string, string> Extension versions keyed by package name, sorted alphabetically.
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

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Configuration';
    }

    /**
     * Returns the `<body>` contents of the {@see phpinfo()} output, rewrapped so the panel's table styles apply.
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

    /**
     * Returns the saved PHP version (`php.version`), or `null` when the snapshot is missing.
     */
    public function getPhpVersion(): string|null
    {
        return self::nestedScalar($this->data, 'php', 'version');
    }

    /**
     * Renders the toolbar summary chip.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/config/summary',
            ['panel' => $this],
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'config';
    }

    /**
     * Returns the saved Yii framework version (`application.yii`), or `null` when the snapshot is missing.
     */
    public function getYiiVersion(): string|null
    {
        return self::nestedScalar($this->data, 'application', 'yii');
    }

    /**
     * Snapshots the framework/PHP/application identity and the installed-extensions roster.
     *
     * @return array{
     *   phpVersion: string,
     *   yiiVersion: string,
     *   application: array{
     *     yii: string,
     *     name: string,
     *     version: string,
     *     language: string,
     *     sourceLanguage: string,
     *     charset: string,
     *     env: string,
     *     debug: bool
     *   },
     *   php: array{
     *     version: string,
     *     xdebug: bool,
     *     apcu: bool,
     *     memcache: bool,
     *     memcached: bool
     *   },
     *   extensions: array<int|string, array<string, mixed>>
     * } Captured configuration snapshot consumed by the detail view and the toolbar.
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

    /**
     * Returns the active application instance via reflection, or `null` when {@see Yii::$app} is unset.
     */
    protected function getApplication(): object|null
    {
        $app = (new ReflectionClass(Yii::class))->getStaticPropertyValue('app');

        return is_object($app) ? $app : null;
    }

    /**
     * Suppresses the per-panel toolbar item: the configuration data is already surfaced through the Yii brand chip
     * (links to this panel) and the dedicated PHP chip (links to `php-info`).
     *
     * @return array<int, array<string, mixed>>|null Always `null`.
     */
    protected function getToolbarItems(): array|null
    {
        return null;
    }

    /**
     * Reads a `$data[$outerKey][$innerKey]` scalar as a string, returning `null` when any segment is missing or the
     * value is not scalar.
     */
    private static function nestedScalar(mixed $data, string $outerKey, string $innerKey): string|null
    {
        if (!is_array($data)) {
            return null;
        }

        $outer = $data[$outerKey] ?? null;

        if (!is_array($outer)) {
            return null;
        }

        $value = $outer[$innerKey] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Narrows the raw extensions list into a map of string-keyed entries, dropping non-array entries and non-string
     * keys inside each entry.
     *
     * @param array<int|string, mixed> $extensions Raw `extensions` slice from {@see Application::$extensions}.
     *
     * @return array<int|string, array<string, mixed>> Sanitized extension entries indexed by their original key.
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
