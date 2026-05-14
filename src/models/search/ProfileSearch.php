<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;
use yii\debug\GridViewConfig;

/**
 * Backs the filter form above the Profiling panel grid of profile blocks captured for the request.
 */
class ProfileSearch extends Base
{
    /**
     * Submitted value for the `category` filter (substring match).
     */
    public string $category = '';
    /**
     * Submitted value for the `info` filter (substring match).
     */
    public string $info = '';

    public function attributeLabels(): array
    {
        return [
            'category' => 'Category',
            'info' => 'Info',
        ];
    }

    public function rules(): array
    {
        return [
            [['category', 'info'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured profile blocks, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param array<int, array<string, mixed>> $models Captured profile records to wrap and filter.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => GridViewConfig::paginationFromRequest(50),
                'sort' => [
                    'attributes' => [
                        'category',
                        'seq',
                        'duration',
                        'info',
                    ],
                    'defaultOrder' => ['duration' => SORT_DESC],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'category', true);
        $this->addCondition($filter, 'info', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
