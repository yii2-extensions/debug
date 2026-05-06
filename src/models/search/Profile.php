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
use yii\debug\GridViewConfig;

/**
 * Search model for current request profiling log.
 */
class Profile extends Base
{
    /**
     * Category attribute input search value.
     */
    public string $category = '';
    /**
     * Info attribute input search value.
     */
    public string $info = '';

    public function attributeLabels()
    {
        return [
            'category' => 'Category',
            'info' => 'Info',
        ];
    }

    public function rules()
    {
        return [
            [['category', 'info'], 'safe'],
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
