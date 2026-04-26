<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Stringable;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\Panel;
use yii\helpers\Html;
use yii\web\{AssetBundle, AssetManager};

use function count;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Debugger panel that collects and displays asset bundles data.
 */
class AssetPanel extends Panel
{
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/assets/detail', ['panel' => $this]);
    }

    public function getName(): string
    {
        return 'Asset Bundles';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/assets/summary', ['panel' => $this]);
    }

    public function getToolbarIcon(): string
    {
        return 'asset';
    }

    public function isEnabled(): bool
    {
        try {
            return Yii::$app->get('assetManager') instanceof AssetManager;
        } catch (InvalidConfigException $exception) {
            return false;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
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
     * Additional formatting for view.
     *
     * @param array<int|string, AssetBundle> $bundles Array of bundles to formatting.
     *
     * @return array<int|string, AssetBundle>
     */
    protected function format(array $bundles): array
    {
        foreach ($bundles as $bundle) {
            $baseUrl = $bundle->baseUrl ?? '';

            foreach ($bundle->css as $key => $file) {
                if (is_string($file)) {
                    $bundle->css[$key] = Html::a($file, $baseUrl . '/' . $file, ['target' => '_blank']);
                }
            }

            foreach ($bundle->js as $key => $file) {
                if (is_string($file)) {
                    $bundle->js[$key] = Html::a($file, $baseUrl . '/' . $file, ['target' => '_blank']);
                }
            }
        }

        return $bundles;
    }

    /**
     * Format associative array of params to simple value.
     *
     * @param array<string, mixed> $params Array of params to formatting.
     *
     * @return array<string, string> Formatted array of params.
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

            $formatted[$param] = Html::tag('strong', "'{$param}' => ") . $value;
        }

        return $formatted;
    }

    /**
     * @return array<int, array<string, mixed>>|null
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
     * @return array<string, mixed>
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
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
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
