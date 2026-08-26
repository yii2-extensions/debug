<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Asset\{AssetBundleRow, AssetSnapshot, ViteChunk, ViteManifest};
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\web\{AssetBundle, AssetManager};

use function count;
use function file_get_contents;
use function is_a;
use function is_array;
use function is_file;
use function is_object;
use function is_string;
use function json_decode;

/**
 * Captures the asset bundles registered on the request for the Asset Bundles panel.
 */
class AssetCollector extends Collector
{
    /**
     * Vite bridge FQCN from `yii2-extensions/inertia`, referenced as a string to avoid a hard package dependency.
     */
    private const string VITE_CLASS = 'yii\inertia\Vite';

    /**
     * Captures every registered asset bundle — plus the Vite manifest when the application wires the
     * `yii2-extensions/inertia` Vite bridge — into the snapshot consumed by the detail view.
     *
     * @return AssetSnapshot|null Captured bundle payload; `null` when the collector never started or the application
     * exposes no `assetManager` component.
     */
    public function capture(): AssetSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        try {
            $assetManager = Yii::$app->get('assetManager');
        } catch (InvalidConfigException) {
            return null;
        }

        if (!$assetManager instanceof AssetManager) {
            return null;
        }

        $bundles = $assetManager->bundles;

        $rows = [];

        if (is_array($bundles)) {
            foreach ($bundles as $name => $bundle) {
                if (!is_string($name) || !$bundle instanceof AssetBundle) {
                    continue;
                }

                $rows[] = AssetBundleRow::fromBundle($name, $this->serializeBundle($bundle));
            }
        }

        return new AssetSnapshot($rows, self::captureVite());
    }

    /**
     * Returns the stable ID pairing this collector with the Asset Bundles panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'asset';
    }

    /**
     * Captures the Vite bridge configuration and its build manifest when the application wires one.
     *
     * @return ViteManifest|null Vite snapshot, or `null` when no Vite component is registered.
     */
    private static function captureVite(): ViteManifest|null
    {
        $component = self::viteComponent();

        if ($component === null) {
            return null;
        }

        // A bridge that declares no manifest path coerces to '', which Yii::getAlias() returns unchanged.
        $manifestPath = (string) Yii::getAlias(
            Coerce::string(ArrayHelper::getValue($component, 'manifestPath')),
            false,
        );

        $devServerUrl = ArrayHelper::getValue($component, 'devServerUrl');

        return new ViteManifest(
            baseUrl: Coerce::string(ArrayHelper::getValue($component, 'baseUrl')),
            devMode: ArrayHelper::getValue($component, 'devMode') === true,
            devServerUrl: is_string($devServerUrl) ? $devServerUrl : null,
            manifestPath: $manifestPath,
            chunks: self::manifestEntries($manifestPath),
        );
    }

    /**
     * Reads the Vite build manifest and narrows every chunk to the fields the panel renders.
     *
     * @param string $manifestPath Absolute path to the build manifest, already resolved from its alias.
     *
     * @return list<ViteChunk> Chunks in manifest order; `[]` when the manifest is missing or unreadable (a dev-server
     * run never writes one).
     */
    private static function manifestEntries(string $manifestPath): array
    {
        if (is_file($manifestPath) === false) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (is_array($decoded) === false) {
            return [];
        }

        $entries = [];

        foreach ($decoded as $name => $chunk) {
            if (!is_string($name) || !is_array($chunk)) {
                continue;
            }

            $entries[] = new ViteChunk(
                name: $name,
                file: Coerce::string($chunk['file'] ?? null),
                cssCount: is_array($chunk['css'] ?? null) ? count($chunk['css']) : 0,
                imports: is_array($chunk['imports'] ?? null) ? count($chunk['imports']) : 0,
                isEntry: ($chunk['isEntry'] ?? false) === true,
            );
        }

        return $entries;
    }

    /**
     * Snapshots the bundle properties consumed by the detail view (paths, files, dependencies).
     *
     * @return array{
     *   basePath: string|null,
     *   baseUrl: string|null,
     *   css: array<array-key, string|array<array-key, mixed>>,
     *   depends: array<class-string>,
     *   js: array<array-key, string|array<array-key, mixed>>,
     *   sourcePath: string|null,
     * } Bundle properties keyed by name.
     */
    private function serializeBundle(AssetBundle $bundle): array
    {
        return [
            'basePath' => $bundle->basePath,
            'baseUrl' => $bundle->baseUrl,
            'css' => $bundle->css,
            'depends' => $bundle->depends,
            'js' => $bundle->js,
            'sourcePath' => $bundle->sourcePath,
        ];
    }

    /**
     * Resolves the first registered application component whose definition points at the Vite bridge.
     */
    private static function viteComponent(): object|null
    {
        foreach (Yii::$app->getComponents() as $id => $definition) {
            $class = match (true) {
                is_object($definition) && !$definition instanceof Closure => $definition::class,
                is_string($definition) => $definition,
                is_array($definition) && is_string($definition['class'] ?? null) => $definition['class'],
                default => null,
            };

            if ($class === null || is_a($class, self::VITE_CLASS, true) === false) {
                continue;
            }

            try {
                $component = Yii::$app->get((string) $id);
            } catch (InvalidConfigException) {
                continue;
            }

            return is_object($component) ? $component : null;
        }

        return null;
    }
}
