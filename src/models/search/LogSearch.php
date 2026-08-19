<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Panel\Dump\DumpRow;
use PHPForge\Debug\Panel\Log\LogRow;
use yii\data\ArrayDataProvider;
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

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'level' => 'Level',
            'category' => 'Category',
            'message' => 'Message',
            'timeSincePrevious' => 'Since previous',
        ];
    }

    #[Override]
    public function formName(): string
    {
        return FilterPrefix::LOG;
    }

    #[Override]
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
     * @param list<DumpRow|LogRow> $models Captured log-shaped rows to wrap and filter.
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
                        'timeSincePrevious' => ['default' => SORT_DESC],
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

        $this->addCondition('level');
        $this->addCondition('category', true);
        $this->addCondition('message', true);

        $dataProvider->allModels = $this->filter($models);

        return $dataProvider;
    }
}
