<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Search model for current request database queries.
 */
class Db extends Base
{
    /**
     * @var string type of the input search value.
     */
    public string $type = '';
    /**
     * @var int query attribute input search value.
     */
    public int $query = 0;

    
    public function rules(): array
    {
        return [
            [['type', 'query'], 'safe'],
        ];
    }

    
    public function attributeLabels(): array
    {
        return [
            'type' => 'Type',
            'query' => 'Query',
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     */
    public function search(array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => false,
            'sort' => [
                'attributes' => ['duration', 'seq', 'type', 'query', 'duplicate'],
            ],
        ]);

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
