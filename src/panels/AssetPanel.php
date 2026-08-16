<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Override;
use PHPForge\Debug\Helper\Coerce;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Strong;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\debug\panels\asset\{AssetBundleNormalizer, AssetBundleRow, AssetSnapshot, ViteChunk, ViteManifest};
use yii\helpers\ArrayHelper;
use yii\web\{AssetBundle, AssetManager};
use yii\web\View;

use function count;
use function file_get_contents;
use function is_a;
use function is_array;
use function is_file;
use function is_object;
use function is_string;
use function json_decode;

/**
 * Captures the asset bundles registered on the request and renders them in the Asset Bundles panel.
 *
 * Stores each bundle's source path, base path, base URL, CSS/JS files, and dependency tree as typed rows, so the
 * detail view renders from a static snapshot.
 *
 * @phpstan-import-type RegisterJsFileOptions from View
 * @phpstan-import-type RegisterCssFileOptions from View
 */
class AssetPanel extends Panel
{
    protected const string ICON = 'asset';
    protected const string NAME = 'Asset Bundles';

    /**
     * Vite bridge FQCN from `yii2-extensions/inertia`, referenced as a string to avoid a hard package dependency.
     */
    private const string VITE_CLASS = 'yii\inertia\Vite';

    private AssetSnapshot|null $snapshot = null;

    /**
     * Captures every registered asset bundle — plus the Vite manifest when the application wires the
     * `yii2-extensions/inertia` Vite bridge — into the snapshot consumed by the detail view.
     */
    public function capture(): AssetSnapshot
    {
        $bundles = Yii::$app->getAssetManager()->bundles;

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
     * @return list<AssetBundleRow> Registered bundles in registration order.
     */
    public function getBundles(): array
    {
        return $this->snapshot?->bundles() ?? [];
    }

    /**
     * Renders the detail view from the normalized bundle summary and the optional Vite manifest snapshot.
     */
    #[Override]
    public function getDetail(): string
    {
        return Yii::$app->view->render(
            'panels/assets/detail',
            [
                'summary' => (new AssetBundleNormalizer())->normalize($this->getBundles()),
                'vite' => $this->snapshot?->vite(),
            ],
            $this,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = AssetSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns whether the application exposes an `assetManager` component the panel can read.
     */
    #[Override]
    public function isEnabled(): bool
    {
        try {
            return Yii::$app->get('assetManager') instanceof AssetManager;
        } catch (InvalidConfigException) {
            return false;
        }
    }

    /**
     * Wraps every CSS/JS file in an anchor pointing at the bundle's base URL, mutating the supplied bundles in place.
     *
     * @param array<int|string, AssetBundle> $bundles Bundles whose `css` / `js` entries should be turned into links.
     *
     * @return array<int|string, AssetBundle> The same bundle map, returned for chaining.
     */
    protected function format(array $bundles): array
    {
        foreach ($bundles as $bundle) {
            $baseUrl = $bundle->baseUrl ?? '';

            foreach ($bundle->css as $key => $file) {
                if (is_string($file)) {
                    $bundle->css[$key] = A::tag()
                        ->href($baseUrl . '/' . $file)
                        ->target('_blank')
                        ->content($file)
                        ->render();
                }
            }

            foreach ($bundle->js as $key => $file) {
                if (is_string($file)) {
                    $bundle->js[$key] = A::tag()
                        ->href($baseUrl . '/' . $file)
                        ->target('_blank')
                        ->content($file)
                        ->render();
                }
            }
        }

        return $bundles;
    }

    /**
     * Formats an associative parameter map for display, stringifying scalar/Stringable values and replacing other
     * types with the result of {@see get_debug_type()}.
     *
     * @param array<string, mixed> $params Parameter map to format.
     *
     * @return array<string, string> Rendered `'param' => value` strings keyed by parameter name.
     */
    protected function formatOptions(array $params): array
    {
        $formatted = [];

        foreach ($params as $param => $value) {
            $value = Coerce::stringOrNull($value) ?? get_debug_type($value);
            $label = Strong::tag()
                ->content("'{$param}' => ")
                ->render();

            $formatted[$param] = "{$label}{$value}";
        }

        return $formatted;
    }

    /**
     * Returns the toolbar item showing the count of registered bundles, or `[]` when none were captured.
     *
     * @return array<int, array<string, mixed>> Single-element list with the `info` chip, or `[]`.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $bundles = $this->getBundles();

        if ($bundles === []) {
            return [];
        }

        return [
            [
                'status' => 'info',
                'title' => 'Number of asset bundles loaded',
                'value' => count($bundles),
            ],
        ];
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
        if ($manifestPath === '' || is_file($manifestPath) === false) {
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
