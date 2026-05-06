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

class Event extends Base
{
    /**
     * Event class attribute input search value.
     */
    public string $class = '';
    /**
     * Static-event filter input search value (`'1'`, `'0'`, or empty for no filter).
     */
    public string $isStatic = '';
    /**
     * Event name attribute input search value.
     */
    public string $name = '';
    /**
     * Sender class attribute input search value.
     */
    public string $senderClass = '';

    public function attributeLabels()
    {
        return [
            'name' => 'Name',
            'class' => 'Class',
            'senderClass' => 'Sender',
            'isStatic' => 'Static',
        ];
    }

    public function rules()
    {
        return [
            [['name', 'class', 'senderClass'], 'string'],
            [['isStatic'], 'boolean'],
            //[['isStatic'], 'filter', 'filter' => function ($value) {return strlen($value) > 0 ? (bool)$value : $value;}],
            [$this->attributes(), 'safe'],
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
                'pagination' => \yii\debug\GridViewConfig::paginationFromRequest(50),
                'sort' => [
                    'attributes' => [
                        'time',
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

        $this->addCondition($filter, 'isStatic');
        $this->addCondition($filter, 'name', true);
        $this->addCondition($filter, 'class', true);
        $this->addCondition($filter, 'senderClass', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
