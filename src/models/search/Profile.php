<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;

/**
 * Search model for current request profiling log.
 */
class Profile extends Base
{
    /**
     * @var string method attribute input search value
     */
    public string $category;
    /**
     * @var int info attribute input search value
     */
    public int $info;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['category', 'info'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'category' => 'Category',
            'info' => 'Info',
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array $params an array of parameter values indexed by parameter names
     * @param array $models data to return provider for
     *
     * @return ArrayDataProvider
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => false,
            'sort' => [
                'attributes' => ['category', 'seq', 'duration', 'info'],
                'defaultOrder' => [
                    'duration' => SORT_DESC,
                ],
            ],
        ]);

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
