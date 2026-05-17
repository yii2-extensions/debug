<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Backs the filter form above the Database panel's query grid.
 */
class DbSearch extends Base
{
    /**
     * Submitted value for the `query` filter (substring match against the SQL text).
     */
    public string $query = '';
    /**
     * Submitted value for the `type` filter (substring match against the statement type).
     */
    public string $type = '';

    public function attributeLabels(): array
    {
        return [
            'type' => 'Type',
            'query' => 'Query',
        ];
    }

    public function rules(): array
    {
        return [
            [['type', 'query'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured queries, applying the active filter values.
     *
     * @param array<int, array<string, mixed>> $models Captured query records to wrap and filter.
     *
     * @return ArrayDataProvider Sortable provider with the filtered query rows.
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
