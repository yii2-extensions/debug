<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Panel\Asset\ViteChunk;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use Yii;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;
use function file_get_contents;
use function get_object_vars;
use function is_a;
use function is_array;
use function is_bool;
use function is_file;
use function is_object;
use function is_string;
use function json_decode;

/**
 * Captures configuration and manifest data from Vite services already loaded during the active request.
 *
 * The collector never constructs lazy application components or contacts a development server. Modern Vite services
 * are inspected through their canonical Yii constructor definition because `PHPForge\Vite\Vite` intentionally keeps
 * its configuration private; unsupported factories and prebuilt instances remain visible with an explicit unavailable
 * inspection state instead of relying on reflection.
 */
class ViteCollector extends Collector
{
    private const string DEVELOPMENT_CONFIGURATION_CLASS = 'PHPForge\Vite\Configuration\DevelopmentConfiguration';
    private const string LEGACY_VITE_CLASS = 'yii\inertia\Vite';
    private const string MODERN_VITE_CLASS = 'PHPForge\Vite\Vite';
    private const string PRODUCTION_CONFIGURATION_CLASS = 'PHPForge\Vite\Configuration\ProductionConfiguration';

    /**
     * Captures every Vite service in loaded-component order.
     *
     * @return ViteSnapshot|null Captured Vite services, or `null` when the collector is idle or no Vite service was
     * loaded during the request.
     */
    public function capture(): ViteSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        $definitions = Yii::$app->getComponents(true);
        $components = [];

        foreach (array_filter(Yii::$app->getComponents(false), is_object(...)) as $id => $component) {
            $id = (string) $id;

            if (is_a($component, self::MODERN_VITE_CLASS)) {
                $components[] = self::modernComponent($id, $component, $definitions[$id] ?? null);

                continue;
            }

            if (is_a($component, self::LEGACY_VITE_CLASS)) {
                $components[] = self::legacyComponent($id, $component);
            }
        }

        return $components === [] ? null : new ViteSnapshot($components);
    }

    /**
     * Returns the stable ID pairing this collector with the Vite panel.
     */
    public function id(): string
    {
        return 'vite';
    }

    /**
     * Captures the public configuration exposed by the former Yii-specific Vite component.
     */
    private static function legacyComponent(string $id, object $component): ViteComponent
    {
        $properties = get_object_vars($component);

        $devMode = ($properties['devMode'] ?? null) === true;

        $resolvedPath = Yii::getAlias(self::string($properties['manifestPath'] ?? null), false);

        $manifestPath = is_string($resolvedPath) ? $resolvedPath : '';

        return new ViteComponent(
            id: $id,
            class: $component::class,
            implementation: ViteComponent::IMPLEMENTATION_LEGACY,
            inspectionAvailable: true,
            mode: $devMode ? ViteComponent::MODE_DEVELOPMENT : ViteComponent::MODE_PRODUCTION,
            entrypoints: self::stringList($properties['entrypoints'] ?? null) ?? [],
            baseUrl: self::string($properties['baseUrl'] ?? null),
            devServerUrl: self::nullableString($properties['devServerUrl'] ?? null),
            manifestPath: $manifestPath,
            includeViteClient: self::nullableBool($properties['includeViteClient'] ?? null),
            modulePreload: self::nullableBool($properties['modulePreload'] ?? null),
            chunks: $devMode ? [] : self::manifestChunks($manifestPath),
        );
    }

    /**
     * Reads one build manifest without executing the Vite resolver or application-owned inline providers.
     *
     * @return list<ViteChunk> Valid named chunk descriptors in manifest order.
     */
    private static function manifestChunks(string $manifestPath): array
    {
        if ($manifestPath === '' || !is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($decoded)) {
            return [];
        }

        $chunks = [];

        foreach ($decoded as $name => $chunk) {
            if (!is_string($name) || !is_array($chunk)) {
                continue;
            }

            $chunks[] = new ViteChunk(
                name: $name,
                file: self::string($chunk['file'] ?? null),
                cssCount: is_array($chunk['css'] ?? null) ? count($chunk['css']) : 0,
                imports: is_array($chunk['imports'] ?? null) ? count($chunk['imports']) : 0,
                isEntry: ($chunk['isEntry'] ?? false) === true,
            );
        }

        return $chunks;
    }

    /**
     * Captures a modern Vite service from the canonical Yii `__construct()` definition.
     */
    private static function modernComponent(string $id, object $component, mixed $definition): ViteComponent
    {
        if (!is_array($definition)) {
            return self::unavailableModernComponent($id, $component);
        }

        $arguments = $definition['__construct()'] ?? null;

        if (!is_array($arguments)) {
            return self::unavailableModernComponent($id, $component);
        }

        if (array_key_exists(0, $arguments)) {
            $configuration = $arguments[0];
            $entrypoints = self::stringList($arguments[1] ?? []);
        } else {
            $configuration = $arguments['configuration'] ?? null;
            $entrypoints = self::stringList($arguments['entrypoints'] ?? []);
        }

        if (!is_object($configuration) || $entrypoints === null) {
            return self::unavailableModernComponent($id, $component);
        }

        $properties = get_object_vars($configuration);

        if (is_a($configuration, self::DEVELOPMENT_CONFIGURATION_CLASS)) {
            $devServerUrl = $properties['devServerUrl'] ?? null;
            $includeViteClient = $properties['includeViteClient'] ?? null;

            if (!is_string($devServerUrl) || !is_bool($includeViteClient)) {
                return self::unavailableModernComponent($id, $component);
            }

            return new ViteComponent(
                id: $id,
                class: $component::class,
                implementation: ViteComponent::IMPLEMENTATION_MODERN,
                inspectionAvailable: true,
                mode: ViteComponent::MODE_DEVELOPMENT,
                entrypoints: $entrypoints,
                baseUrl: '',
                devServerUrl: $devServerUrl,
                manifestPath: '',
                includeViteClient: $includeViteClient,
                modulePreload: null,
                chunks: [],
            );
        }

        if (is_a($configuration, self::PRODUCTION_CONFIGURATION_CLASS)) {
            $manifestPath = $properties['manifestPath'] ?? null;
            $baseUrl = $properties['assetBaseUrl'] ?? null;
            $modulePreload = $properties['modulePreload'] ?? null;

            if (!is_string($manifestPath) || !is_string($baseUrl) || !is_bool($modulePreload)) {
                return self::unavailableModernComponent($id, $component);
            }

            return new ViteComponent(
                id: $id,
                class: $component::class,
                implementation: ViteComponent::IMPLEMENTATION_MODERN,
                inspectionAvailable: true,
                mode: ViteComponent::MODE_PRODUCTION,
                entrypoints: $entrypoints,
                baseUrl: $baseUrl,
                devServerUrl: null,
                manifestPath: $manifestPath,
                includeViteClient: null,
                modulePreload: $modulePreload,
                chunks: self::manifestChunks($manifestPath),
            );
        }

        return self::unavailableModernComponent($id, $component);
    }

    private static function nullableBool(mixed $value): bool|null
    {
        return is_bool($value) ? $value : null;
    }

    private static function nullableString(mixed $value): string|null
    {
        return is_string($value) ? $value : null;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>|null A reindexed list, or `null` when any configured value is not a string.
     */
    private static function stringList(mixed $values): array|null
    {
        if (!is_array($values)) {
            return null;
        }

        foreach ($values as $value) {
            if (!is_string($value)) {
                return null;
            }
        }

        return array_values($values);
    }

    private static function unavailableModernComponent(string $id, object $component): ViteComponent
    {
        return new ViteComponent(
            id: $id,
            class: $component::class,
            implementation: ViteComponent::IMPLEMENTATION_MODERN,
            inspectionAvailable: false,
            mode: ViteComponent::MODE_UNKNOWN,
            entrypoints: [],
            baseUrl: '',
            devServerUrl: null,
            manifestPath: '',
            includeViteClient: null,
            modulePreload: null,
            chunks: [],
        );
    }
}
