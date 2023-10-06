<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Closure;
use Yii;
use yii\debug\Panel;
use yii\helpers\Html;
use yii\web\AssetBundle;

use function array_walk;

/**
 * Debugger panel that collects and displays asset bundles data.
 */
class AssetPanel extends Panel
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Asset Bundles';
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/assets/summary', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/assets/detail', ['panel' => $this]);
    }

    /**
     * {@inheritdoc}
     */
    public function save(): mixed
    {
        $bundles = Yii::$app->view->assetManager->bundles;

        if (empty($bundles)) { // bundles can be false
            return [];
        }

        $data = [];

        foreach ($bundles as $name => $bundle) {
            if ($bundle instanceof AssetBundle) {
                $bundleData = (array)$bundle;
                if (
                    isset($bundleData['publishOptions']['beforeCopy']) &&
                    $bundleData['publishOptions']['beforeCopy'] instanceof Closure
                ) {
                    $bundleData['publishOptions']['beforeCopy'] = Closure::class;
                }

                if (
                    isset($bundleData['publishOptions']['afterCopy']) &&
                    $bundleData['publishOptions']['afterCopy'] instanceof Closure
                ) {
                    $bundleData['publishOptions']['afterCopy'] = Closure::class;
                }

                $data[$name] = $bundleData;
            }
        }
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return isset(Yii::$app->view->assetManager) && Yii::$app->view->assetManager;
    }

    /**
     * Additional formatting for view.
     *
     * @param AssetBundle[] $bundles Array of bundles to formatting.
     *
     * @return AssetBundle[]
     */
    protected function format(array $bundles): array
    {
        foreach ($bundles as $bundle) {
            array_walk($bundle->css, static function (&$file, $key, $userData) {
                $file = Html::a($file, $userData->baseUrl . '/' . $file, ['target' => '_blank']);
            }, $bundle);

            array_walk($bundle->js, static function (&$file, $key, $userData) {
                $file = Html::a($file, $userData->baseUrl . '/' . $file, ['target' => '_blank']);
            }, $bundle);

            array_walk($bundle->depends, static function (&$depend) {
                $depend = Html::a($depend, '#' . $depend);
            });

            $this->formatOptions($bundle->publishOptions);
            $this->formatOptions($bundle->jsOptions);
            $this->formatOptions($bundle->cssOptions);
        }

        return $bundles;
    }

    /**
     * Format associative array of params to simple value.
     *
     * @param array $params
     *
     * @return array
     */
    protected function formatOptions(array &$params): array
    {
        foreach ($params as $param => $value) {
            $params[$param] = Html::tag('strong', '\'' . $param . '\' => ') . $value;
        }

        return $params;
    }
}
