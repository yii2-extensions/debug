<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Event
 */
class Event extends Base
{
    /**
     * @var bool whether event is static or not.
     */
    public bool $isStatic = false;
    public string $name = '';
    public string $class = '';
    public string $senderClass = '';

    public function rules(): array
    {
        return [
            [['name', 'class', 'senderClass'], 'string'],
            [['isStatic'], 'boolean'],
            //[['isStatic'], 'filter', 'filter' => function ($value) {return strlen($value) > 0 ? (bool)$value : $value;}],
            [$this->attributes(), 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Name',
            'class' => 'Class',
            'senderClass' => 'Sender',
            'isStatic' => 'Static',
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array $params an array of parameter values indexed by parameter names
     * @param array $models data to return provider for
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => false,
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
