<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Config\{ConfigDataNormalizer, ConfigSnapshot};
use Yii;
use yii\debug\Panel;

use function is_array;
use function is_scalar;
use function is_string;
use function ksort;

/**
 * Renders the application configuration and runtime environment captured by the Configuration collector.
 *
 * Presents the Yii framework / PHP / application identity and the installed-extensions roster through the detail view,
 * the toolbar's `php-info` link, and the brand-chip version readouts; data acquisition lives in
 * {@see \yii\debug\collectors\ConfigCollector}.
 */
class ConfigPanel extends Panel
{
    protected const string ICON = 'config';
    protected const string NAME = 'Configuration';

    private ConfigSnapshot|null $snapshot = null;

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
     * @return array<array-key, mixed>
     */
    private function payload(): array
    {
        return $this->snapshot?->data() ?? [];
    }
}
