<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\ArrayDataProvider;
use yii\debug\components\search\Filter;
use yii\debug\GridViewConfig;

/**
 * Backs the filter form above the Log panel grid of the active request's log messages.
 */
class LogSearch extends Base
{
    /**
     * Submitted value for the `category` filter (substring match).
     */
    public string $category = '';
    /**
     * Submitted value for the `level` filter (exact match).
     */
    public string $level = '';
    /**
     * Submitted value for the `message` filter (substring match).
     */
    public string $message = '';

    public function attributeLabels(): array
    {
        return [
            'level' => 'Level',
            'category' => 'Category',
            'message' => 'Message',
            'time_since_previous' => 'Since previous',
        ];
    }

    public function rules(): array
    {
        return [
            [['level', 'message', 'category'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured log messages, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param array<int, array<string, mixed>> $models Captured log entries to wrap and filter.
     */
    public function search(array $params, array $models): ArrayDataProvider
    {
        $dataProvider = new ArrayDataProvider(
            [
                'allModels' => $models,
                'pagination' => GridViewConfig::paginationFromRequest(50),
                'sort' => [
                    'attributes' => [
                        'time',
                        'time_since_previous' => ['default' => SORT_DESC],
                        'level',
                        'category',
                        'message',
                    ],
                    'defaultOrder' => ['time' => SORT_ASC],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $filter = new Filter();

        $this->addCondition($filter, 'level');
        $this->addCondition($filter, 'category', true);
        $this->addCondition($filter, 'message', true);

        $dataProvider->allModels = $filter->filter($models);

        return $dataProvider;
    }
}
