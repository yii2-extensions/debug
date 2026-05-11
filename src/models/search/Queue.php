<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Search model for the Queue debug panel — drives the filter form above the cards list and produces an
 * {@see ArrayDataProvider} so the panel never renders a paginated list larger than the active page size, even when a
 * single request pushes hundreds of jobs.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
class Queue extends Base
{
    /**
     * Yii component id filter (`'queue'`, `'queueRedis'`, ...) — substring match.
     */
    public string $componentId = '';
    /**
     * Friendly driver name filter (`'Sync'`, `'Database'`, `'Redis'`, ...) — substring match.
     */
    public string $driverName = '';
    /**
     * Lifecycle phase filter (`'push'`, `'exec'`, `'error'`); empty means no filter.
     */
    public string $eventType = '';
    /**
     * Job FQCN filter — substring match, so `'Hello'` finds `'app\\jobs\\HelloJob'`.
     */
    public string $jobClass = '';
    /**
     * Backend job-id filter — substring match against the id returned by `$queue->push($job)`.
     */
    public string $jobId = '';

    public function attributeLabels(): array
    {
        return [
            'eventType' => 'Status',
            'driverName' => 'Driver',
            'componentId' => 'Component',
            'jobClass' => 'Job',
            'jobId' => 'ID',
        ];
    }

    public function rules(): array
    {
        return [
            [['eventType', 'driverName', 'componentId', 'jobClass', 'jobId'], 'safe'],
        ];
    }

    /**
     * Returns the data provider with the captured queue records, filtered when the form was submitted with values
     * and validated.
     *
     * @param array<int|string, mixed> $params
     * @param array<int, array<string, mixed>> $models
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => ['pageSize' => 25],
                'sort' => [
                    'attributes' => ['eventType', 'driverName', 'componentId', 'jobClass', 'jobId', 'time', 'duration'],
                    'defaultOrder' => ['time' => SORT_ASC],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        // `eventType` and `componentId` are exact-match dropdowns; substring matching would pollute results (`'push'`
        // would also match `'pushed-by-worker'` if such a value ever appeared).
        $this->addCondition($filter, 'eventType', false);
        $this->addCondition($filter, 'componentId', false);
        $this->addCondition($filter, 'driverName', true);
        $this->addCondition($filter, 'jobClass', true);
        $this->addCondition($filter, 'jobId', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
