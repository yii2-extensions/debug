<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\debug\models\search\Profile;
use yii\debug\Panel;
use yii\log\Logger;

use function memory_get_peak_usage;
use function microtime;
use function number_format;
use function sprintf;

/**
 * Debugger panel that collects and displays performance profiling info.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 *
 * @since 2.0
 */
class ProfilingPanel extends Panel
{
    /**
     * @var array current request profile timings.
     */
    private array $_models = [];

    public function getName(): string
    {
        return 'Profiling';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/profile/summary', [
            'memory' => sprintf('%.3f MB', $this->data['memory'] / 1048576),
            'time' => number_format($this->data['time'] * 1000) . ' ms',
            'panel' => $this,
        ]);
    }

    public function getDetail(): string
    {
        $searchModel = new Profile();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render('panels/profile/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'memory' => sprintf('%.3f MB', $this->data['memory'] / 1048576),
            'time' => number_format($this->data['time'] * 1000) . ' ms',
        ]);
    }

    public function save(): mixed
    {
        $messages = $this->getLogMessages(Logger::LEVEL_PROFILE);
        return [
            'memory' => memory_get_peak_usage(),
            'time' => microtime(true) - YII_BEGIN_TIME,
            'messages' => $messages,
        ];
    }

    /**
     * Returns array of profiling models that can be used in a data provider.
     */
    protected function getModels(): array
    {
        return $this->_models;
    }
}
