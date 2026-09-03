<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Data\{FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Profile\ProfileRow;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;

use function array_key_exists;
use function is_finite;
use function is_numeric;
use function trim;

/**
 * Backs the shared filters for the Profiling Timeline and span-details grid.
 */
class ProfileSearch extends Base
{
    /**
     * Submitted value for the `category` filter (substring match).
     */
    public string $category = '';
    /**
     * Submitted value for the minimum `duration` filter, in milliseconds.
     */
    public string $duration = '';
    /**
     * Submitted value for the `info` filter (substring match).
     */
    public string $info = '';

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'category' => 'Category',
            'duration' => 'Min duration (ms)',
            'info' => 'Info',
        ];
    }

    #[Override]
    public function formName(): string
    {
        return FilterPrefix::PROFILE;
    }

    #[Override]
    public function rules(): array
    {
        return [
            [['category', 'duration', 'info'], 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured profiling spans, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param list<ProfileRow> $models Captured profiling spans to wrap and filter.
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

        if (!array_key_exists(FilterPrefix::PROFILE, $params)) {
            return $dataProvider;
        }

        $normalizedParams = [
            FilterPrefix::PROFILE => QueryInput::group($params, FilterPrefix::PROFILE),
        ];

        if (!($this->load($normalizedParams) && $this->validate())) {
            return $dataProvider;
        }

        $this->addCondition('category', true);
        $this->addCondition('info', true);

        $duration = trim($this->duration);

        if ($duration !== '' && is_numeric($duration)) {
            $minimumDuration = (float) $duration;

            if (is_finite($minimumDuration) && $minimumDuration >= 0.0) {
                $this->duration = $duration;
                $this->addMinimumCondition('duration', $minimumDuration);
            } else {
                $this->duration = '';
            }
        } else {
            $this->duration = '';
        }

        $dataProvider->allModels = $this->filter($models);

        return $dataProvider;
    }
}
