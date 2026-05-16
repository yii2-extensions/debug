<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Stringable;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Strong;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\debug\panels\asset\AssetBundleNormalizer;
use yii\web\{AssetBundle, AssetManager};

use function count;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Captures the asset bundles registered on the request and renders them in the Asset Bundles panel.
 *
 * Stores the serialized bundle map (with `Closure` callbacks turned into label markers) so the detail view can render
 * each bundle's source path, base path, base URL, CSS/JS files, and dependency tree from a static snapshot.
 *
 * @phpstan-import-type RegisterJsFileOptions from \yii\web\View
 * @phpstan-import-type RegisterCssFileOptions from \yii\web\View
 *
 * @extends Panel<
 *   array<
 *     string,
 *     array{
 *       basePath?: string|null,
 *       baseUrl?: string|null,
 *       css?: array<array-key, string|array<array-key, mixed>>,
 *       cssOptions?: RegisterCssFileOptions,
 *       depends?: array<class-string>,
 *       js?: array<array-key, string|array<array-key, mixed>>,
 *       jsOptions?: RegisterJsFileOptions,
 *       publishOptions?: array<string, mixed>,
 *       sourcePath?: string|null,
 *     }
 *   >
 * >
 */
class AssetPanel extends Panel
{
    /**
     * Renders the detail view from the normalized bundle summary.
     */
    public function getDetail(): string
    {
        $data = is_array($this->data) ? $this->data : [];

        $summary = (new AssetBundleNormalizer())->normalize($data);

        return Yii::$app->view->render(
            'panels/assets/detail',
            ['summary' => $summary],
            $this,
        );
    }

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Asset Bundles';
    }

    /**
     * Renders the toolbar summary chip.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/assets/summary',
            ['panel' => $this],
            $this,
        );
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
    public function isEnabled(): bool
    {
        try {
            return Yii::$app->get('assetManager') instanceof AssetManager;
        } catch (InvalidConfigException $exception) {
            return false;
        }
    }

    /**
     * Serializes every registered asset bundle into the panel-data shape consumed by the detail view.
     *
     * @return array<string, array{
     *   basePath?: string|null,
     *   baseUrl?: string|null,
     *   css?: array<array-key, string|array<array-key, mixed>>,
     *   cssOptions?: RegisterCssFileOptions,
     *   depends?: array<class-string>,
     *   js?: array<array-key, string|array<array-key, mixed>>,
     *   jsOptions?: RegisterJsFileOptions,
     *   publishOptions?: array<string, mixed>,
     *   sourcePath?: string|null,
     * }> Serialized bundles indexed by FQCN, or `[]` when no bundles were registered.
     */
    public function save(): array
    {
        $bundles = Yii::$app->getAssetManager()->bundles;

        if ($bundles === false || $bundles === []) {
            return [];
        }

        $data = [];

        foreach ($bundles as $name => $bundle) {
            if (!is_string($name) || !$bundle instanceof AssetBundle) {
                continue;
            }

            $data[$name] = $this->serializeBundle($bundle);
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
     * Returns the toolbar item showing the count of registered bundles, or `null` when none were captured.
     *
     * @return array<int, array<string, mixed>>|null Single-element list with the `info` chip, or `null`.
     */
    protected function getToolbarItems(): array|null
    {
        if (!is_array($this->data) || $this->data === []) {
            return null;
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
}
