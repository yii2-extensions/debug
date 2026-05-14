<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;
use yii\debug\GridViewConfig;

use function in_array;

/**
 * Backs the filter form on the debug index page that lists every captured request manifest entry.
 */
class DebugSearch extends Base
{
    /**
     * Submitted value for the `ajax` filter (exact match).
     */
    public string $ajax = '';
    /**
     * @var list<int> HTTP status codes flagged as severe in the request grid.
     */
    public array $criticalCodes = [400, 404, 500];
    /**
     * Submitted value for the `ip` filter (substring match).
     */
    public string $ip = '';
    /**
     * Submitted value for the `mailCount` filter (operator-aware numeric match).
     */
    public string $mailCount = '';
    /**
     * Submitted value for the `method` filter (exact match).
     */
    public string $method = '';
    /**
     * Submitted value for the `sqlCount` filter (operator-aware numeric match).
     */
    public string $sqlCount = '';
    /**
     * Submitted value for the `statusCode` filter (exact or operator-aware numeric match).
     */
    public string $statusCode = '';
    /**
     * Submitted value for the `tag` filter (substring match).
     */
    public string $tag = '';
    /**
     * Submitted value for the `url` filter (substring match).
     */
    public string $url = '';

    public function attributeLabels(): array
    {
        return [
            'tag' => 'Tag',
            'processingTime' => 'Processing Time',
            'peakMemory' => 'Peak Memory',
            'ip' => 'Ip',
            'method' => 'Method',
            'ajax' => 'Ajax',
            'url' => 'url',
            'statusCode' => 'Status code',
            'sqlCount' => 'Query Count',
            'mailCount' => 'Mail Count',
        ];
    }

    /**
     * Returns whether the given status code is flagged as critical in {@see $criticalCodes}.
     */
    public function isCodeCritical(int $code): bool
    {
        return in_array($code, $this->criticalCodes, true);
    }

    public function rules(): array
    {
        return [
            [['tag', 'ip', 'method', 'ajax', 'url', 'statusCode', 'sqlCount', 'mailCount'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the manifest entries, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param array<int, array<string, mixed>> $models Manifest entries to wrap and filter.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'sort' => [
                    'attributes' => [
                        'method',
                        'ip',
                        'tag',
                        'time',
                        'statusCode',
                        'sqlCount',
                        'mailCount',
                        'processingTime',
                        'peakMemory',
                    ],
                ],
                'pagination' => GridViewConfig::paginationFromRequest(50),
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'tag', true);
        $this->addCondition($filter, 'ip', true);
        $this->addCondition($filter, 'method');
        $this->addCondition($filter, 'ajax');
        $this->addCondition($filter, 'url', true);
        $this->addCondition($filter, 'statusCode');
        $this->addCondition($filter, 'sqlCount');
        $this->addCondition($filter, 'mailCount');

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
