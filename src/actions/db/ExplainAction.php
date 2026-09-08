<?php

declare(strict_types=1);

namespace yii\debug\actions\db;

use PHPForge\Debug\Panel\Db\{DbMessage, QueryRow};
use Yii;
use yii\db\Exception as DbException;
use yii\debug\actions\Action;
use yii\debug\panels\DbPanel;
use yii\web\NotFoundHttpException;

use function ctype_digit;
use function is_string;

/**
 * Renders the EXPLAIN plan for a single captured SQL query.
 *
 * Maps to the `db-explain` route registered by {@see DbPanel::init()}; consumes `tag` (request snapshot) and `seq` (the
 * captured row's own sequence number) to locate the SQL statement and execute the driver-appropriate EXPLAIN command.
 * Malformed input answers `400` and an unknown tag or sequence answers `404`, both with an empty body, so the inline
 * AJAX workflow never renders an exception page inside the grid.
 *
 * SQLite uses `EXPLAIN QUERY PLAN`; MySQL and PostgreSQL use plain `EXPLAIN`. Statements that produce no useful plan
 * and database rejections are handled as successful diagnostic responses so the captured query and the escaped reason
 * replace the result instead of a generic transport failure.
 */
class ExplainAction extends Action
{
    /**
     * Runs the action.
     *
     * @param mixed $seq Sequence number of the captured row to explain; anything but a digit string answers `400`.
     * @param mixed $tag Request tag whose debug snapshot holds the query; anything but a non-empty string answers
     * `400`.
     * @param DbPanel $panel Panel instance resolved from the debug module's service locator by the standalone-action
     * binder.
     *
     * @return string Rendered view with the EXPLAIN results, or `''` for the `400` and `404` responses.
     */
    public function run(mixed $seq, mixed $tag, DbPanel $panel): string
    {
        if (!is_string($tag) || $tag === '' || !is_string($seq) || !ctype_digit($seq)) {
            return $this->respondEmpty(400);
        }

        try {
            $this->loadData($tag);
        } catch (NotFoundHttpException) {
            return $this->respondEmpty(404);
        }

        $row = $this->findRow($panel, $seq);

        if ($row === null) {
            return $this->respondEmpty(404);
        }

        [$error, $results] = $this->explain($panel, $row);

        $this->prepareShell($panel, $tag);

        $params = ['error' => $error, 'query' => $row->query, 'results' => $results];

        return Yii::$app->request->isAjax
            ? $this->renderPartial('db-explain', $params)
            : $this->render('db-explain', $params);
    }

    /**
     * Runs the driver-appropriate EXPLAIN command for an explainable row.
     *
     * @param DbPanel $panel Panel owning the DB connection the plan runs on.
     * @param QueryRow $row Captured row to explain.
     *
     * @return array{0: string|null, 1: array<array-key, mixed>} Diagnostic message (`null` on success) paired with the
     * plan rows.
     */
    private function explain(DbPanel $panel, QueryRow $row): array
    {
        if ($row->isExplainable() === false) {
            return [DbMessage::EXPLAIN_UNAVAILABLE->value, []];
        }

        $db = $panel->getDb();
        $explainPrefix = $db->getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';

        try {
            return [null, $db->createCommand("{$explainPrefix}{$row->query}")->queryAll()];
        } catch (DbException $exception) {
            return [$exception->getMessage(), []];
        }
    }

    /**
     * Returns the captured row whose sequence number matches the requested one exactly, or `null` when none does.
     *
     * @param DbPanel $panel Panel holding the hydrated rows.
     * @param string $seq Requested sequence number, compared as a string so `'02'` never matches sequence `2`.
     */
    private function findRow(DbPanel $panel, string $seq): QueryRow|null
    {
        foreach ($panel->getRows() as $row) {
            if ((string) $row->seq === $seq) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Answers with the given status code and an empty body.
     *
     * @param int $statusCode HTTP status code to send.
     */
    private function respondEmpty(int $statusCode): string
    {
        Yii::$app->getResponse()->setStatusCode($statusCode);

        return '';
    }
}
