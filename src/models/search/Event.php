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
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.14
 */
class Event extends Base
{
    /**
     * @var string|null event class attribute input search value
     */
    public $class;
    /**
     * @var bool|null whether event is static or not.
     */
    public $isStatic;
    /**
     * @var string|null event name attribute input search value
     */
    public $name;
    /**
     * @var string|null sender class attribute input search value
     */
    public $senderClass;

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
     * @param array<int|string, mixed> $params an array of parameter values indexed by parameter names
     * @param array<int, array<string, mixed>> $models data to return provider for
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => \yii\debug\GridViewConfig::paginationFromRequest(50),
            'sort' => [
                'attributes' => ['time', 'level', 'category', 'message'],
                'defaultOrder' => [
                    'time' => SORT_ASC,
                ],
            ],
        ]);

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
