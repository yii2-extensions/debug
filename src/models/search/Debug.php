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

/**
 * Search model for requests manifest data.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Mark Jebri <mark.github@yandex.ru>
 * @since 2.0
 */
class Debug extends Base
{
    /**
     * @var int ajax attribute input search value
     */
    public $ajax;
    /**
     * @var array<int, int> critical codes, used to determine grid row options.
     */
    public $criticalCodes = [400, 404, 500];
    /**
     * @var string ip attribute input search value
     */
    public $ip;
    /**
     * @var int total mail count attribute input search value
     */
    public $mailCount;
    /**
     * @var string method attribute input search value
     */
    public $method;
    /**
     * @var int sql count attribute input search value
     */
    public $sqlCount;
    /**
     * @var string status code attribute input search value
     */
    public $statusCode;
    /**
     * @var string tag attribute input search value
     */
    public $tag;
    /**
     * @var string url attribute input search value
     */
    public $url;

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
     * @param array<int|string, mixed> $params an array of parameter values indexed by parameter names
     * @param array<int, array<string, mixed>> $models data to return provider for
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'sort' => [
                'attributes' => ['method', 'ip', 'tag', 'time', 'statusCode', 'sqlCount', 'mailCount', 'processingTime', 'peakMemory'],
            ],
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

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
