<?php

declare(strict_types=1);

namespace yii\debug\actions\db;

use Yii;
use yii\base\Action;
use yii\debug\controllers\DefaultController;
use yii\debug\panels\DbPanel;
use yii\web\HttpException;

/**
 * Renders the EXPLAIN plan for a single captured SQL query.
 *
 * Maps to the `db-explain` route registered by {@see DbPanel::getActions()}; consumes `tag` (request snapshot) and
 * `seq` (index into the panel's timings array) to locate the SQL statement and execute the driver-appropriate EXPLAIN
 * command.
 *
 * SQLite uses `EXPLAIN QUERY PLAN`; MySQL and PostgreSQL use plain `EXPLAIN`.
 */
class ExplainAction extends Action
{
    /**
     * Database panel instance used to recover the captured query and the active DB connection.
     */
    public DbPanel|null $panel = null;

    /**
     * Runs the action.
     *
     * @param string $seq Sequence number of the timing entry to explain.
     * @param string $tag Request tag whose debug snapshot holds the query.
     *
     * @throws HttpException When the panel was not wired, the controller is not the debug `DefaultController`, or the
     * timing entry cannot be found for the given `seq`.
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
        $explainPrefix = $db->getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
        $results = $db->createCommand("{$explainPrefix}{$query}")->queryAll();

        $params = ['query' => $query, 'results' => $results];

        return Yii::$app->request->isAjax
            ? $controller->renderPartial('db-explain', $params)
            : $controller->render('db-explain', $params);
    }
}
