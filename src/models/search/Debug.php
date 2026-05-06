<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;
use yii\debug\GridViewConfig;

/**
 * Search model for requests manifest data.
 */
class Debug extends Base
{
    /**
     * Ajax attribute input search value.
     */
    public string $ajax = '';
    /**
     * Critical status codes used to flag grid rows as severe.
     *
     * @var list<int>
     */
    public array $criticalCodes = [400, 404, 500];
    /**
     * IP attribute input search value.
     */
    public string $ip = '';
    /**
     * Mail count attribute input search value.
     */
    public string $mailCount = '';
    /**
     * Method attribute input search value.
     */
    public string $method = '';
    /**
     * SQL count attribute input search value.
     */
    public string $sqlCount = '';
    /**
     * Status code attribute input search value.
     */
    public string $statusCode = '';
    /**
     * Tag attribute input search value.
     */
    public string $tag = '';
    /**
     * URL attribute input search value.
     */
    public string $url = '';

    public function attributeLabels()
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
     * Checks if code is critical.
     */
    public function isCodeCritical(int $code): bool
    {
        return in_array($code, $this->criticalCodes, true);
    }

    public function rules()
    {
        return [
            [['tag', 'ip', 'method', 'ajax', 'url', 'statusCode', 'sqlCount', 'mailCount'], 'safe'],
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array<int|string, mixed> $params An array of parameter values indexed by parameter names
     * @param array<int, array<string, mixed>> $models Sata to return provider for
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
