<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Override;
use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Panel\Event\EventRow;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;

/**
 * Backs the filter form above the Event panel grid of captured framework events.
 */
class EventSearch extends Base
{
    /**
     * Submitted value for the event-class filter (substring match against the listener class FQCN).
     */
    public string $class = '';
    /**
     * Submitted value for the static-event filter: `'1'`, `'0'`, or `''` (no filter).
     */
    public string $isStatic = '';
    /**
     * Submitted value for the `name` filter (substring match against the event name).
     */
    public string $name = '';
    /**
     * Submitted value for the sender-class filter (substring match against the sender FQCN).
     */
    public string $senderClass = '';

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'name' => 'Name',
            'class' => 'Class',
            'senderClass' => 'Sender',
            'isStatic' => 'Static',
        ];
    }

    #[Override]
    public function formName(): string
    {
        return FilterPrefix::EVENT;
    }

    #[Override]
    public function rules(): array
    {
        return [
            [['name', 'class', 'senderClass'], 'string'],
            [['isStatic'], 'boolean'],
            [$this->attributes(), 'safe'],
        ];
    }

    /**
     * Returns an {@see ArrayDataProvider} over the captured events, applying the loaded filter values.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by {@see Model::load()}.
     * @param list<EventRow> $models Captured event rows to wrap and filter.
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
                        'name',
                        'class',
                        'senderClass',
                        'isStatic',
                    ],
                    'defaultOrder' => ['time' => SORT_ASC],
                ],
            ],
        );

        if (!($this->load($params) && $this->validate())) {
            return $dataProvider;
        }

        $this->addCondition('isStatic');
        $this->addCondition('name', true);
        $this->addCondition('class', true);
        $this->addCondition('senderClass', true);

        $dataProvider->allModels = $this->filter($models);

        return $dataProvider;
    }
}
