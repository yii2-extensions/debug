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
 * Search model for current request log.
 */
class Log extends Base
{
    /**
     * Category attribute input search value.
     */
    public string $category = '';
    /**
     * Level attribute input search value.
     */
    public string $level = '';
    /**
     * Message attribute input search value.
     */
    public string $message = '';

    public function attributeLabels()
    {
        return [
            'level' => 'Level',
            'category' => 'Category',
            'message' => 'Message',
            'time_since_previous' => 'Since previous',
        ];
    }

    public function rules()
    {
        return [
            [['level', 'message', 'category'], 'safe'],
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array<int|string, mixed> $params An array of parameter values indexed by parameter names.
     * @param array<int, array<string, mixed>> $models Data to return provider for.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => GridViewConfig::paginationFromRequest(50),
                'sort' => [
                    'attributes' => [
                        'time',
                        'time_since_previous' => ['default' => SORT_DESC],
                        'level',
                        'category',
                        'message',
                    ],
                    'defaultOrder' => ['time' => SORT_ASC],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'level');
        $this->addCondition($filter, 'category', true);
        $this->addCondition($filter, 'message', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
