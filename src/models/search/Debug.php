<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

use function in_array;

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
     * @var string tag attribute input search value.
     */
    public string $tag = '';
    /**
     * @var string ip attribute input search value.
     */
    public string $ip = '';
    /**
     * @var string method attribute input search value.
     */
    public string $method = '';
    /**
     * @var int ajax attribute input search value.
     */
    public int $ajax = -1;
    /**
     * @var string url attribute input search value.
     */
    public string $url = '';
    /**
     * @var string status code attribute input search value.
     */
    public string $statusCode = '';
    /**
     * @var int sql count attribute input search value.
     */
    public int $sqlCount = 0;
    /**
     * @var int total mail counts attribute input search value.
     */
    public int $mailCount = 0;
    /**
     * @var array critical codes, used to determine grid row options.
     */
    public array $criticalCodes = [400, 404, 500];

    public function rules(): array
    {
        return [
            [['tag', 'ip', 'method', 'ajax', 'url', 'statusCode', 'sqlCount', 'mailCount'], 'safe'],
        ];
    }

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
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array $params an array of parameter values indexed by parameter names.
     * @param array $models data to return provider for.
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

    /**
     * Checks if code is critical.
     */
    public function isCodeCritical(int $code): bool
    {
        return in_array($code, $this->criticalCodes);
    }
}
