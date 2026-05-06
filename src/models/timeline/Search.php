<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
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
 */
class Search extends Base
{
    /**
     * Category attribute input search value.
     */
    public string $category = '';
    /**
     * Minimum duration filter value (milliseconds), as submitted by the form.
     */
    public string $duration = '';

    /**
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        return [
            'duration' => 'Duration ≥',
        ];
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['category', 'duration'], 'safe'],
        ];
    }

    /**
     * Returns data provider with filled models. Filter applied if needed.
     *
     * @param array<int|string, mixed> $params An array of parameter values indexed by parameter names.
     */
    public function search(array $params, TimelinePanel $panel): DataProvider
    {
        $models = $panel->getModels();
        $dataProvider = new DataProvider(
            $panel,
            [
                'allModels' => $models,
                'sort' => [
                    'attributes' => [
                        'category',
                        'timestamp',
                    ],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'category', true);

        $duration = (float) $this->duration;

        if ($duration > 0) {
            $filter->addMatcher('duration', new GreaterThanOrEqual(['value' => $duration / 1000]));
        }

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
