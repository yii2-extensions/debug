<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\storage\RequestSummary;
use yii\debug\widgets\history\HistoryRow;

use function array_map;
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

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'tag' => 'Tag',
            'processingTime' => 'Processing Time',
            'peakMemory' => 'Peak Memory',
            'ip' => 'IP',
            'method' => 'Method',
            'ajax' => 'AJAX',
            'url' => 'URL',
            'statusCode' => 'Status',
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

    #[Override]
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
     * @param list<RequestSummary> $models Manifest entries to wrap and filter.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $rows = array_map(HistoryRow::fromSummary(...), $models);

        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $rows,
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

        $this->addCondition('tag', true);
        $this->addCondition('ip', true);
        $this->addCondition('method');
        $this->addCondition('ajax');
        $this->addCondition('url', true);
        $this->addCondition('statusCode');
        $this->addCondition('sqlCount');
        $this->addCondition('mailCount');

        $dataProvider->allModels = $this->filter($rows);

        return $dataProvider;
    }
}
