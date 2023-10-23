<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\timeline;

use yii\debug\components\search\Filter;
use yii\debug\components\search\matchers\GreaterThanOrEqual;
use yii\debug\models\search\Base;
use yii\debug\panels\TimelinePanel;

/**
 * Search model for timeline data.
 *
 * @author Dmitriy Bashkarev <dmitriy@bashkarev.com>
 *
 * @since 2.0.8
 */
class Search extends Base
{
    /**
     * @var string attribute search.
     */
    public string $category = '';
    /**
     * @var int attribute search.
     */
    public int $duration = 0;

    public function rules(): array
    {
        return [
            [['category', 'duration'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'duration' => 'Duration ≥',
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     */
    public function search(array $params, TimelinePanel $panel): DataProvider
    {
        $models = $panel->models;

        $dataProvider = new DataProvider($panel, [
            'allModels' => $models,
            'sort' => [
                'attributes' => ['category', 'timestamp'],
            ],
        ]);

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'category', true);

        if ($this->duration > 0) {
            $filter->addMatcher('duration', new GreaterThanOrEqual(['value' => $this->duration / 1000]));
        }

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
