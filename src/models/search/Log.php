<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Search model for current request log.
 */
class Log extends Base
{
    /**
     * @var string ip attribute input search value.
     */
    public string $level = '';
    /**
     * @var string method attribute input search value.
     */
    public string $category = '';
    /**
     * @var int message attribute input search value.
     */
    public int $message = 0;

    public function rules(): array
    {
        return [
            [['level', 'message', 'category'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'level' => 'Level',
            'category' => 'Category',
            'message' => 'Message',
            'time_since_previous' => 'Since previous',
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
            'pagination' => false,
            'sort' => [
                'attributes' => [
                    'time',
                    'time_since_previous' => [
                        'default' => SORT_DESC,
                    ],
                    'level',
                    'category',
                    'message',
                ],
                'defaultOrder' => [
                    'time' => SORT_ASC,
                ],
            ],
        ]);

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
