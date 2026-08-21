<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use Yii;
use yii\base\Application;
use yii\debug\VersionResolver;

use function extension_loaded;
use function is_array;
use function is_object;
use function is_string;

/**
 * Captures the application configuration and runtime environment for the Configuration panel.
 *
 * Snapshots the Yii framework / PHP / application identity and the installed-extensions roster.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\ConfigCollector())->capture();
 * ```
 */
class ConfigCollector extends Collector
{
    /**
     * Snapshots the framework/PHP/application identity and the installed-extensions roster.
     *
     * @return ConfigSnapshot|null Captured configuration payload; `null` when the collector never started.
     */
    public function capture(): ConfigSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

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

        return ConfigSnapshot::capture([
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
        ]);
    }

    /**
     * Returns the stable ID pairing this collector with the Configuration panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\ConfigCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'config';
    }

    /**
     * Returns the active application instance, or `null` when the framework slot does not contain an object.
     */
    protected function getApplication(): object|null
    {
        $app = self::applicationValue();

        return is_object($app) ? $app : null;
    }

    /**
     * Returns the untyped framework application slot without assuming its PHPDoc type.
     */
    private static function applicationValue(): mixed
    {
        return Yii::$app;
    }

    /**
     * Narrows the raw extensions list into the typed shape defined by {@see Application::$extensions}, dropping
     * non-array entries and unrecognized keys.
     *
     * @param array<int|string, mixed> $extensions Raw `extensions` slice from {@see Application::$extensions}.
     *
     * @return array<int|string, array{
     *   name?: string,
     *   version?: string,
     *   bootstrap?: string|array<string, mixed>,
     *   alias?: array<string, string>,
     * }> Sanitized extension entries indexed by their original key.
     */
    private static function normalizeExtensions(array $extensions): array
    {
        $normalized = [];

        foreach ($extensions as $name => $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $entry = [];
            $rawName = $extension['name'] ?? null;
            $rawVersion = $extension['version'] ?? null;
            $bootstrap = $extension['bootstrap'] ?? null;
            $rawAlias = $extension['alias'] ?? null;

            if (is_string($rawName)) {
                $entry['name'] = $rawName;
            }

            if (is_string($rawVersion)) {
                $entry['version'] = $rawVersion;
            }

            if (is_string($bootstrap)) {
                $entry['bootstrap'] = $bootstrap;
            } elseif (is_array($bootstrap)) {
                $entry['bootstrap'] = Coerce::stringKeyedArray($bootstrap);
            }

            if (is_array($rawAlias)) {
                $aliases = [];

                foreach ($rawAlias as $aliasKey => $aliasPath) {
                    if (is_string($aliasKey) && is_string($aliasPath)) {
                        $aliases[$aliasKey] = $aliasPath;
                    }
                }

                $entry['alias'] = $aliases;
            }

            $normalized[$name] = $entry;
        }

        return $normalized;
    }
}
