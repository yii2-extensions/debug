<?php

declare(strict_types=1);

namespace yii\debug\actions\queue;

use Yii;
use yii\debug\actions\Action;
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
     * Runs the action.
     *
     * @param string $seq Zero-based index of the record inside the panel's saved records array.
     * @param string $tag Request tag whose debug snapshot holds the record.
     * @param QueuePanel $panel Panel instance resolved from the debug module's service locator by the
     * standalone-action binder.
     *
     * @throws HttpException When the record cannot be found for the given `seq`.
     *
     * @return string Rendered view with the queue job details.
     */
    public function run(string $seq, string $tag, QueuePanel $panel): string
    {
        $this->loadData($tag);

        $records = $panel->getRecords();

        $seqKey = (int) $seq;

        if (!isset($records[$seqKey])) {
            throw new HttpException(
                404,
                'Queue job record not found.',
            );
        }

        $record = $records[$seqKey];

        $this->prepareShell($panel, $tag);

        $params = ['record' => $record, 'tag' => $tag];

        return Yii::$app->request->isAjax
            ? $this->renderPartial('queue-job', $params)
            : $this->render('queue-job', $params);
    }
}
