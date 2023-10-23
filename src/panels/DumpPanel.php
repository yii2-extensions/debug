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
use yii\debug\models\search\Log;
use yii\debug\Panel;
use yii\helpers\Html;
use yii\helpers\VarDumper;
use yii\log\Logger;

use function call_user_func;
use function is_callable;

/**
 * Dump panel that collects and displays debug messages (Logger::LEVEL_TRACE).
 *
 * @author Pistej <pistej2@gmail.com>
 * @author Simon Karlen <simi.albi@outlook.com>
 * @since 2.1.0
 */
class DumpPanel extends Panel
{
    /**
     * @var array the message categories to filter by. If empty array, it means all categories are allowed.
     */
    public array $categories = ['application'];
    /**
     * @var bool whether the result should be syntax-highlighted.
     */
    public bool $highlight = true;
    /**
     * @var int maximum depth that the dumper should go into the variable.
     */
    public int $depth = 10;
    /**
     * @var callable callback that replaces the built-in var dumper. The signature of this function should be:
     * `function (mixed $data, DumpPanel $panel)`
     */
    public $varDumpCallback;

    /**
     * @var array log messages extracted to array as models, to use with data provider.
     */
    private array $_models = [];

    public function getName(): string
    {
        return 'Dump';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/dump/summary', ['panel' => $this]);
    }

    public function getDetail(): string
    {
        $searchModel = new Log();
        $dataProvider = $searchModel->search(Yii::$app->request->getQueryParams(), $this->getModels());

        return Yii::$app->view->render('panels/dump/detail', [
            'dataProvider' => $dataProvider,
            'panel' => $this,
            'searchModel' => $searchModel,
        ]);
    }

    public function save(): mixed
    {
        $except = [];
        if (isset($this->module->panels['router'])) {
            $except = $this->module->panels['router']->getCategories();
        }

        $messages = $this->getLogMessages(Logger::LEVEL_TRACE, $this->categories, $except);

        foreach ($messages as &$message) {
            if (!isset($message[0])) {
                continue;
            }

            $message[0] = $this->varDump($message[0]);
        }

        return $messages;
    }

    /**
     * Called by `save()` to format the dumped variable.
     */
    public function varDump($var)
    {
        if (is_callable($this->varDumpCallback)) {
            return call_user_func($this->varDumpCallback, $var, $this);
        }

        $message = VarDumper::dumpAsString($var, $this->depth, $this->highlight);

        //don't encode highlighted variables
        if (!$this->highlight) {
            $message = Html::encode($message);
        }

        return $message;
    }

    /**
     * Returns an array of models that represents logs of the current request.
     * Can be used with data providers, such as \yii\data\ArrayDataProvider.
     *
     * @param bool $refresh if you need to build models from log messages and refresh them.
     */
    protected function getModels(bool $refresh = false): array
    {
        if ($refresh) {
            $this->_models = [];

            foreach ($this->data as $message) {
                $this->_models[] = [
                    'message' => $message[0],
                    'level' => $message[1],
                    'category' => $message[2],
                    'time' => $message[3] * 1000, // time in milliseconds
                    'trace' => $message[4],
                ];
            }
        }

        return $this->_models;
    }
}
