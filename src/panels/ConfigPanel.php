<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\Coerce;
use ReflectionClass;
use Yii;
use yii\base\Application;
use yii\debug\{Panel, VersionResolver};
use yii\debug\panels\config\{ConfigDataNormalizer, ConfigSnapshot};

use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function ksort;

/**
 * Captures the application configuration and runtime environment shown in the Configuration panel.
 *
 * Stores the Yii framework / PHP / application identity and the installed-extensions roster, then surfaces it through
 * the detail view, the toolbar's `php-info` link, and the brand-chip version readouts.
 */
class ConfigPanel extends Panel
{
    protected const string ICON = 'config';
    protected const string NAME = 'Configuration';

    private ConfigSnapshot|null $snapshot = null;

    /**
     * Snapshots the framework/PHP/application identity and the installed-extensions roster.
     */
    public function capture(): ConfigSnapshot
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
     * Renders the detail view from the normalized configuration summary.
     */
    #[Override]
    public function getDetail(): string
    {
        $data = $this->payload();

        $summary = (new ConfigDataNormalizer())->normalize($data, $this->getExtensions());

        return Yii::$app->view->render(
            'panels/config/detail',
            ['summary' => $summary],
            $this,
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

        $panelData = $this->payload();

        $extensions = is_array($panelData['extensions'] ?? null) ? $panelData['extensions'] : [];

        foreach ($extensions as $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $name = $extension['name'] ?? null;
            $version = $extension['version'] ?? null;

            if (is_string($name) && is_string($version)) {
                $data[$name] = $version;
            }
        }

        ksort($data);

        return $data;
    }

    /**
     * Returns the saved PHP version (`php.version`), or `null` when the snapshot is missing.
     */
    public function getPhpVersion(): string|null
    {
        return self::nestedScalar($this->payload(), 'php', 'version');
    }

    /**
     * Returns the saved Yii framework version (`application.yii`), or `null` when the snapshot is missing.
     */
    public function getYiiVersion(): string|null
    {
        return self::nestedScalar($this->payload(), 'application', 'yii');
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = ConfigSnapshot::fromArray($payload, "$.panels.{$this->id}");
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
     * @return array<int, array<string, mixed>> Always `[]`.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        return [];
    }

    /**
     * Reads a `$data[$outerKey][$innerKey]` scalar as a string, returning `null` when any segment is missing or the
     * value is not scalar.
     *
     * @param array<array-key, mixed> $data Captured configuration payload.
     */
    private static function nestedScalar(array $data, string $outerKey, string $innerKey): string|null
    {
        $outer = $data[$outerKey] ?? null;

        if (!is_array($outer)) {
            return null;
        }

        $value = $outer[$innerKey] ?? null;

        return is_scalar($value) ? (string) $value : null;
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

    /**
     * @return array<array-key, mixed>
     */
    private function payload(): array
    {
        return $this->snapshot?->data() ?? [];
    }
}
