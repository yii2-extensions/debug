<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use Yii;
use yii\debug\models\search\EventSearch;
use yii\debug\Panel;

use function count;

/**
 * Renders the framework events captured by the Events collector.
 *
 * Presents each fired event's name, class, sender, and capture timestamp in the Events grid; data acquisition lives
 * in {@see \yii\debug\collectors\EventCollector}.
 */
class EventPanel extends Panel
{
    protected const string ICON = 'events';
    protected const string NAME = 'Events';

    private EventSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the events grid.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new EventSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->get(), $this->getEvents());

        return Yii::$app->view->render(
            'panels/event/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * @return list<EventRow> Captured event rows in fire order.
     */
    public function getEvents(): array
    {
        return $this->snapshot?->entries() ?? [];
    }

    public function hasEvents(): bool
    {
        return $this->getEvents() !== [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = EventSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns the toolbar item showing the total event count, or `[]` when none were captured.
     *
     * @return array<int, array<string, mixed>> Single-element list with the count, or `[]`.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $eventCount = count($this->getEvents());

        if ($eventCount === 0) {
            return [];
        }

        return [['value' => $eventCount]];
    }
}
