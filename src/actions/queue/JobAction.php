<?php

declare(strict_types=1);

namespace yii\debug\actions\queue;

use Yii;
use yii\base\Action;
use yii\debug\controllers\DefaultController;
use yii\debug\panels\queue\JobRecordNormalizer;
use yii\debug\panels\QueuePanel;
use yii\web\HttpException;

use function array_values;
use function is_array;

/**
 * Renders the dedicated detail page for a single captured queue record (one job event).
 *
 * Maps to the `queue-job` route registered by {@see QueuePanel::init()}; consumes `tag` (request snapshot) and `seq`
 * (zero-based index into `$panel->data['records']`).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
class JobAction extends Action
{
    /**
     * Queue panel instance, used to recover the captured records for the active tag.
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
     */
    public function run(string $seq, string $tag): string
    {
        if ($this->panel === null) {
            throw new HttpException(500, 'QueuePanel instance is not set for JobAction.');
        }

        $controller = $this->controller;

        if (!$controller instanceof DefaultController) {
            throw new HttpException(500, 'queue-job action must run inside the debug DefaultController.');
        }

        $controller->loadData($tag);

        $records = is_array($this->panel->data) && is_array($this->panel->data['records'] ?? null)
            ? array_values($this->panel->data['records'])
            : [];

        $seqKey = (int) $seq;

        if (!isset($records[$seqKey]) || !is_array($records[$seqKey])) {
            throw new HttpException(404, 'Queue job record not found.');
        }

        $record = JobRecordNormalizer::from($records[$seqKey]);
        $themeContext = $controller->primeThemeContext();

        $params = [
            'activePanel' => $this->panel,
            'debugTheme' => $themeContext['theme'],
            'manifest' => $controller->getManifest(),
            'panel' => $this->panel,
            'panels' => $controller->module->panels,
            'record' => $record,
            'summary' => $controller->summary,
            'tag' => $tag,
            'themeIconMoon' => $themeContext['moon'],
            'themeIconSun' => $themeContext['sun'],
        ];

        return Yii::$app->request->isAjax
            ? $controller->renderPartial('queue-job', $params)
            : $controller->render('queue-job', $params);
    }
}
