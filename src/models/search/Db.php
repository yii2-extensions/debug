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
 * Search model for current request database queries.
 */
class Db extends Base
{
    /**
     * Query attribute input search value.
     */
    public string $query = '';
    /**
     * Type of the input search value.
     */
    public string $type = '';

    public function attributeLabels()
    {
        return [
            'type' => 'Type',
            'query' => 'Query',
        ];
    }

    public function rules()
    {
        return [
            [['type', 'query'], 'safe'],
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array<int, array<string, mixed>> $models Data to return provider for.
     *
     * @return ArrayDataProvider Data provider with filled models.
     */
    public function search(array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => false,
                'sort' => [
                    'attributes' => [
                        'duration',
                        'seq',
                        'type',
                        'query',
                        'duplicate',
                        'rows',
                    ],
                ],
            ],
        );

        if (!$this->validate()) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'type', true);
        $this->addCondition($filter, 'query', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
