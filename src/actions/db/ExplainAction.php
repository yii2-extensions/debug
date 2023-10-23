<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\actions\db;

use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\debug\panels\DbPanel;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

use function array_keys;
use function array_map;
use function htmlspecialchars;
use function implode;

/**
 * ExplainAction provides EXPLAIN information for SQL queries
 *
 * @author Laszlo <github@lvlconsultancy.nl>
 * @since 2.0.6
 */
class ExplainAction extends Action
{
    public DbPanel $panel;

    /**
     * Runs the action.
     *
     * @throws Exception
     * @throws NotFoundHttpException if the view file cannot be found
     * @throws InvalidConfigException
     * @throws HttpException
     */
    public function run(string $seq, string $tag): string
    {
        $this->controller->loadData($tag);

        $timings = $this->panel->calculateTimings();

        if (!isset($timings[$seq])) {
            throw new HttpException(404, 'Log message not found.');
        }

        $query = $timings[$seq]['info'];

        $results = $this->panel->getDb()->createCommand('EXPLAIN ' . $query)->queryAll();

        $output[] = '<table class="table"><thead><tr>' . implode(array_map(static function ($key): string {
            return '<th>' . $key . '</th>';
        }, array_keys($results[0]))) . '</tr></thead><tbody>';

        foreach ($results as $result) {
            $output[] = '<tr>' . implode(array_map(static function ($value): string {
                return '<td>' . (empty($value) ? 'NULL' : htmlspecialchars($value)) . '</td>';
            }, $result)) . '</tr>';
        }

        $output[] = '</tbody></table>';

        return implode($output);
    }
}
