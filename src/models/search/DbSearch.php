<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Panel\Db\QueryRow;
use yii\data\ArrayDataProvider;

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

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'type' => 'Type',
            'query' => 'Query',
        ];
    }

    #[Override]
    public function formName(): string
    {
        return FilterPrefix::DB;
    }

    #[Override]
    public function rules(): array
    {
        return [
            [['type', 'query'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured queries, applying the active filter values.
     *
     * @param list<QueryRow> $models Captured query rows to wrap and filter.
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

        $this->addCondition('type', true);
        $this->addCondition('query', true);

        $dataProvider->allModels = $this->filter($models);

        return $dataProvider;
    }
}
