<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\debug\components\search\Filter;
use yii\debug\components\search\matchers\GreaterThanOrEqual;
use yii\debug\models\timeline\DataProvider;
use yii\debug\panels\TimelinePanel;

/**
 * Backs the filter form above the Timeline panel and produces the geometry-aware data provider.
 */
class TimelineSearch extends Base
{
    /**
     * Submitted value for the `category` filter (substring match).
     */
    public string $category = '';
    /**
     * Submitted minimum-duration threshold, in milliseconds.
     */
    public string $duration = '';

    /**
     * @return array<string, string> Form labels keyed by attribute name.
     */
    public function attributeLabels(): array
    {
        return [
            'duration' => 'Duration ≥',
        ];
    }

    /**
     * @return array<int, array<int|string, mixed>> Validation rules consumed by {@see Model::validate()}.
     */
    public function rules(): array
    {
        return [
            [['category', 'duration'], 'safe'],
        ];
    }

    /**
     * Returns the timeline {@see DataProvider} for the active panel, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param TimelinePanel $panel Panel supplying the captured timeline rows and request geometry.
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
