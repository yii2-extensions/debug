<?php

declare(strict_types=1);

namespace yii\debug\actions\db;

use Yii;
use yii\debug\actions\Action;
use yii\debug\exception\Message;
use yii\debug\panels\DbPanel;
use yii\web\HttpException;

/**
 * Renders the EXPLAIN plan for a single captured SQL query.
 *
 * Maps to the `db-explain` route registered by {@see DbPanel::init()}; consumes `tag` (request snapshot) and `seq`
 * (index into the panel's captured rows) to locate the SQL statement and execute the driver-appropriate EXPLAIN
 * command.
 *
 * SQLite uses `EXPLAIN QUERY PLAN`; MySQL and PostgreSQL use plain `EXPLAIN`.
 */
class ExplainAction extends Action
{
    /**
     * Runs the action.
     *
     * @param string $seq Sequence number of the timing entry to explain.
     * @param string $tag Request tag whose debug snapshot holds the query.
     * @param DbPanel $panel Panel instance resolved from the debug module's service locator by the standalone-action
     * binder.
     *
     * @throws HttpException When the timing entry cannot be found for the given `seq`.
     *
     * @return string Rendered view with the EXPLAIN results.
     */
    public function run(string $seq, string $tag, DbPanel $panel): string
    {
        $this->loadData($tag);

        $rows = $panel->getRows();

        $seqKey = (int) $seq;

        if (!isset($rows[$seqKey])) {
            throw new HttpException(404, Message::LOG_MESSAGE_NOT_FOUND->getMessage());
        }

        $query = $rows[$seqKey]->query;

        $db = $panel->getDb();
        $explainPrefix = $db->getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
        $results = $db->createCommand("{$explainPrefix}{$query}")->queryAll();
        $this->prepareShell($panel, $tag);

        $params = ['query' => $query, 'results' => $results];

        return Yii::$app->request->isAjax
            ? $this->renderPartial('db-explain', $params)
            : $this->render('db-explain', $params);
    }
}
