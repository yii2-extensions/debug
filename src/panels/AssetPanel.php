<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Closure;
use Yii;
use yii\debug\Panel;
use yii\helpers\Html;
use yii\web\AssetBundle;

use function array_walk;

/**
 * Debugger panel that collects and displays asset bundles data.
 *
 * @author Artur Fursa <arturfursa@gmail.com>
 *
 * @since 2.0
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

    public function isEnabled(): bool
    {
        return isset(Yii::$app->view->assetManager) && Yii::$app->view->assetManager;
    }

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
     */
    protected function formatOptions(array &$params): array
    {
        foreach ($params as $param => $value) {
            $params[$param] = Html::tag('strong', '\'' . $param . '\' => ') . $value;
        }

        return $params;
    }
}
