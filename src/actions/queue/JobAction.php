<?php

declare(strict_types=1);

namespace yii\debug\actions\queue;

use Yii;
use yii\base\Action;
use yii\debug\controllers\DefaultController;
use yii\debug\panels\QueuePanel;
use yii\web\HttpException;

/**
 * Renders the detail page for a single captured queue record.
 *
 * Maps to the `queue-job` route registered by {@see QueuePanel::init()}; consumes `tag` (request snapshot) and `seq`
 * (zero-based index into the Queue panel snapshot records).
 */
class JobAction extends Action
{
    /**
     * Queue panel instance used to recover the captured records for the active tag.
     */
    public QueuePanel|null $panel = null;

    /**
     * Runs the action.
     *
     * @param string $seq Zero-based index of the record inside the panel's saved records array.
     * @param string $tag Request tag whose debug snapshot holds the record.
     *
     * @throws HttpException When the panel was not wired, the controller is not the debug `DefaultController`, or the
     * record cannot be found for the given `seq`.
     *
     * @return string Rendered view with the queue job details.
     */
    public function run(string $seq, string $tag): string
    {
        if ($this->panel === null) {
            throw new HttpException(
                500,
                'QueuePanel instance is not set for JobAction.',
            );
        }

        $controller = $this->controller;

        if (!$controller instanceof DefaultController) {
            throw new HttpException(
                500,
                'queue-job action must run inside the debug DefaultController.',
            );
        }

        $controller->loadData($tag);

        $records = $this->panel->getRecords();

        $seqKey = (int) $seq;

        if (!isset($records[$seqKey])) {
            throw new HttpException(
                404,
                'Queue job record not found.',
            );
        }

        $record = $records[$seqKey];

        $controller->prepareShell($this->panel, $tag);

        $params = ['record' => $record, 'tag' => $tag];

        return Yii::$app->request->isAjax
            ? $controller->renderPartial('queue-job', $params)
            : $controller->render('queue-job', $params);
    }
}
