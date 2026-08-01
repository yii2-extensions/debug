<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Override;
use Stringable;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Strong;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\debug\panels\asset\AssetBundleNormalizer;
use yii\helpers\ArrayHelper;
use yii\web\{AssetBundle, AssetManager};
use yii\web\View;

use function array_filter;
use function array_values;
use function count;
use function file_get_contents;
use function is_a;
use function is_array;
use function is_file;
use function is_object;
use function is_scalar;
use function is_string;
use function json_decode;

/**
 * Captures the asset bundles registered on the request and renders them in the Asset Bundles panel.
 *
 * Stores the serialized bundle map (with `Closure` callbacks turned into label markers) so the detail view can render
 * each bundle's source path, base path, base URL, CSS/JS files, and dependency tree from a static snapshot.
 *
 * @phpstan-import-type RegisterJsFileOptions from View
 * @phpstan-import-type RegisterCssFileOptions from View
 *
 * @extends Panel<array<string, array<string, mixed>>>
 */
class AssetPanel extends Panel
{
    /**
     * Vite bridge FQCN from `yii2-extensions/inertia`, referenced as a string to avoid a hard package dependency.
     */
    private const string VITE_CLASS = 'yii\inertia\Vite';
    /**
     * Reserved panel-data key holding the Vite manifest snapshot; never a valid bundle FQCN.
     */
    private const string VITE_KEY = '@vite';

    /**
     * Renders the detail view from the normalized bundle summary and the optional Vite manifest snapshot.
     */
    #[Override]
    public function getDetail(): string
    {
        $data = is_array($this->data) ? $this->data : [];

        $vite = is_array($data[self::VITE_KEY] ?? null) ? $data[self::VITE_KEY] : null;

        unset($data[self::VITE_KEY]);

        $summary = (new AssetBundleNormalizer())->normalize($data);

        return Yii::$app->view->render(
            'panels/assets/detail',
            ['summary' => $summary, 'vite' => $vite],
            $this,
        );
    }

    /**
     * Returns the panel display name.
     */
    #[Override]
    public function getName(): string
    {
        return 'Asset Bundles';
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'asset';
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
     * Serializes every registered asset bundle — plus the Vite manifest snapshot when the application wires the
     * `yii2-extensions/inertia` Vite bridge — into the panel-data shape consumed by the detail view.
     *
     * @return array<string, array<string, mixed>> Serialized bundles indexed by FQCN, with the optional Vite
     * snapshot under the reserved `@vite` key; `[]` when nothing was captured.
     */
    public function save(): array
    {
        $bundles = Yii::$app->getAssetManager()->bundles;

        $data = [];

        if (is_array($bundles)) {
            foreach ($bundles as $name => $bundle) {
                if (!is_string($name) || !$bundle instanceof AssetBundle) {
                    continue;
                }

                $data[$name] = $this->serializeBundle($bundle);
            }
        }

        $vite = self::captureVite();

        if ($vite !== null) {
            $data[self::VITE_KEY] = $vite;
        }

        return $data;
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
            if (is_scalar($value) || $value instanceof Stringable) {
                $value = (string) $value;
            } else {
                $value = get_debug_type($value);
            }

            $formatted[$param] = Strong::tag()
                ->content("'{$param}' => ")
                ->render()
                . $value;
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
        if (!is_array($this->data) || $this->data === []) {
            return [];
        }

        return [
            [
                'status' => 'info',
                'title' => 'Number of asset bundles loaded',
                'value' => count($this->data),
            ],
        ];
    }

    /**
     * Captures the Vite bridge configuration and its build manifest when the application wires one.
     *
     * @return array{
     *   baseUrl: string,
     *   devMode: bool,
     *   devServerUrl: string|null,
     *   entries: array<string, array{css: list<string>, file: string, imports: int, isEntry: bool}>,
     *   entrypoints: list<string>,
     *   manifestPath: string,
     * }|null Vite snapshot, or `null` when no Vite component is registered.
     */
    private static function captureVite(): array|null
    {
        $component = self::viteComponent();

        if ($component === null) {
            return null;
        }

        $manifestPath = ArrayHelper::getValue($component, 'manifestPath');
        $manifestPath = is_string($manifestPath) ? (string) Yii::getAlias($manifestPath, false) : '';

        $entries = [];

        if ($manifestPath !== '' && is_file($manifestPath)) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($decoded)) {
                foreach ($decoded as $name => $chunk) {
                    if (!is_string($name) || !is_array($chunk)) {
                        continue;
                    }

                    $css = is_array($chunk['css'] ?? null)
                        ? array_values(array_filter($chunk['css'], is_string(...)))
                        : [];

                    $entries[$name] = [
                        'css' => $css,
                        'file' => is_string($chunk['file'] ?? null) ? $chunk['file'] : '',
                        'imports' => is_array($chunk['imports'] ?? null) ? count($chunk['imports']) : 0,
                        'isEntry' => ($chunk['isEntry'] ?? false) === true,
                    ];
                }
            }
        }

        $baseUrl = ArrayHelper::getValue($component, 'baseUrl');
        $devMode = ArrayHelper::getValue($component, 'devMode');
        $devServerUrl = ArrayHelper::getValue($component, 'devServerUrl');
        $entrypoints = ArrayHelper::getValue($component, 'entrypoints');

        return [
            'baseUrl' => is_string($baseUrl) ? $baseUrl : '',
            'devMode' => $devMode === true,
            'devServerUrl' => is_string($devServerUrl) ? $devServerUrl : null,
            'entries' => $entries,
            'entrypoints' => is_array($entrypoints)
                ? array_values(array_filter($entrypoints, is_string(...)))
                : [],
            'manifestPath' => $manifestPath,
        ];
    }

    /**
     * Snapshots the bundle properties consumed by the detail view (paths, files, options, dependencies).
     *
     * @return array{
     *   basePath: string|null,
     *   baseUrl: string|null,
     *   css: array<array-key, string|array<array-key, mixed>>,
     *   cssOptions: RegisterCssFileOptions,
     *   depends: array<class-string>,
     *   js: array<array-key, string|array<array-key, mixed>>,
     *   jsOptions: RegisterJsFileOptions,
     *   publishOptions: array<string, mixed>,
     *   sourcePath: string|null,
     * } Bundle properties keyed by name.
     */
    private function serializeBundle(AssetBundle $bundle): array
    {
        return [
            'basePath' => $bundle->basePath,
            'baseUrl' => $bundle->baseUrl,
            'css' => $bundle->css,
            'cssOptions' => $bundle->cssOptions,
            'depends' => $bundle->depends,
            'js' => $bundle->js,
            'jsOptions' => $bundle->jsOptions,
            'publishOptions' => $this->serializeOptions($bundle->publishOptions),
            'sourcePath' => $bundle->sourcePath,
        ];
    }

    /**
     * Replaces `beforeCopy` / `afterCopy` closures with the literal label `\Closure`, so the panel data stays
     * serializable.
     *
     * @param array<string, mixed> $options Raw publish-options map.
     *
     * @return array<string, mixed> Options map with closure callbacks replaced.
     */
    private function serializeOptions(array $options): array
    {
        foreach (['beforeCopy', 'afterCopy'] as $name) {
            if (isset($options[$name]) && $options[$name] instanceof Closure) {
                $options[$name] = '\Closure';
            }
        }

        return $options;
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
                is_array($definition) && is_string($definition['__class'] ?? null) => $definition['__class'],
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
