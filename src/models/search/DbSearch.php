<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Panel\Db\{DbMessage, QueryRow};
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;

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

    /**
     * @return array<string, string> Form labels keyed by attribute name.
     */
    #[Override]
    public function attributeLabels(): array
    {
        return [
            'type' => DbMessage::TYPE->value,
            'query' => DbMessage::QUERY->value,
        ];
    }

    /**
     * @return string Query-string prefix that scopes this form's filter parameters.
     */
    #[Override]
    public function formName(): string
    {
        return FilterPrefix::DB;
    }

    /**
     * @return array<int, array<int|string, mixed>> Validation rules consumed by {@see Model::validate()}.
     */
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
        $dataProvider = new QueryRowDataProvider(
            [
                'allModels' => $models,
                'pagination' => GridViewConfig::paginationFromRequest(50),
                'sort' => [
                    'attributes' => [
                        'duration',
                        'seq',
                        'type',
                        'query',
                        'duplicate',
                        'rows',
                    ],
                    'params' => GridViewConfig::sortParams(),
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
