<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Panel\Profile\ProfileRow;
use yii\data\ArrayDataProvider;
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

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'category' => 'Category',
            'info' => 'Info',
        ];
    }

    #[Override]
    public function formName(): string
    {
        return 'Profile';
    }

    #[Override]
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
     * @param list<ProfileRow> $models Captured profile blocks to wrap and filter.
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

        $this->addCondition('category', true);
        $this->addCondition('info', true);

        $dataProvider->allModels = $this->filter($models);

        return $dataProvider;
    }
}
