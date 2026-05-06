<?php

declare(strict_types=1);

namespace yii\debug\actions\db;

use Yii;
use yii\base\Action;
use yii\debug\controllers\DefaultController;
use yii\debug\panels\DbPanel;
use yii\web\HttpException;

/**
 * ExplainAction provides EXPLAIN information for SQL queries
 */
class ExplainAction extends Action
{
    /**
     * Database panel instance, which will be used to retrieve the database connection and calculate timings.
     */
    public DbPanel|null $panel = null;

    /**
     * Runs the action.
     *
     * @param string $seq Sequence number of the log message to explain.
     * @param string $tag Tag of the log message to explain.
     *
     * @throws HttpException if the controller is not an instance of DefaultController, or if the log message is not
     * found.
     *
     * @return string Rendered view with the EXPLAIN results.
     */
    public function run(string $seq, string $tag): string
    {
        if ($this->panel === null) {
            throw new HttpException(
                500,
                'DbPanel instance is not set for ExplainAction.',
            );
        }

        $controller = $this->controller;

        if (!$controller instanceof DefaultController) {
            throw new HttpException(
                500,
                'EXPLAIN action must run inside the debug DefaultController.',
            );
        }

        $controller->loadData($tag);

        $timings = $this->panel->calculateTimings();

        $seqKey = (int) $seq;

        if (!isset($timings[$seqKey])) {
            throw new HttpException(404, 'Log message not found.');
        }

        $query = $timings[$seqKey]['info'];

        $db = $this->panel->getDb();

        /**
         * SQLite bare `EXPLAIN` dumps VDBE bytecode (Init/Halt/Goto…) which is useless to application developers.
         * `EXPLAIN QUERY PLAN` is the human-readable equivalent.
         * MySQL/PostgreSQL already return a usable plan from plain `EXPLAIN`.
         */
        $explainPrefix = $db->getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
        $results = $db->createCommand("{$explainPrefix}{$query}")->queryAll();

        $params = ['query' => $query, 'results' => $results];

        return Yii::$app->request->isAjax
            ? $controller->renderPartial('db-explain', $params)
            : $controller->render('db-explain', $params);
    }
}
