<?php

declare(strict_types=1);

namespace yii\debug\actions\db;

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

        $output[] = '<table class="table"><thead><tr>'
            . implode(
                array_map(
                    static fn($key): string => "<th>{$key}</th>",
                    array_keys($results[0]),
                )
            )
            . '</tr></thead><tbody>';

        foreach ($results as $result) {
            $output[] = '<tr>' . implode(
                array_map(
                    static fn($value): string => '<td>' . (empty($value) ? 'NULL' : htmlspecialchars($value)) . '</td>',
                    $result,
                )
            )
            . '</tr>';
        }

        $output[] = '</tbody></table>';

        return implode($output);
    }
}
