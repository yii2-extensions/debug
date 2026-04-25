<?php

declare(strict_types=1);

namespace yii\debug\actions\db;

use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\debug\panels\DbPanel;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

/**
 * ExplainAction provides EXPLAIN information for SQL queries
 */
class ExplainAction extends Action
{
    /**
     * @var DbPanel
     */
    public $panel;

    /**
     * Runs the action.
     *
     * @param string $seq
     * @param string $tag
     *
     * @throws HttpException
     * @throws Exception
     * @throws NotFoundHttpException if the view file cannot be found
     * @throws InvalidConfigException
     *
     * @return string
     */
    public function run($seq, $tag)
    {
        $this->controller->loadData($tag);

        $timings = $this->panel->calculateTimings();

        if (!isset($timings[$seq])) {
            throw new HttpException(404, 'Log message not found.');
        }

        $query = $timings[$seq]['info'];
        $results = $this->panel->getDb()->createCommand('EXPLAIN ' . $query)->queryAll();

        $params = ['query' => $query, 'results' => $results];

        return Yii::$app->request->isAjax
            ? $this->controller->renderPartial('db-explain', $params)
            : $this->controller->render('db-explain', $params);
    }
}
