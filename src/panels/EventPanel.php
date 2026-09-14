<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use PHPForge\Debug\Panel\{PanelIcon, PanelTitle};
use PHPForge\Debug\Storage\HydrationException;
use Yii;
use yii\debug\models\search\EventSearch;
use yii\debug\Panel;

use function count;

/**
 * Renders the framework events captured by the Events collector.
 *
 * Presents each fired event's name, class, sender, and capture timestamp in the Events grid; data acquisition lives in
 * {@see \yii\debug\collectors\EventCollector}.
 */
class EventPanel extends Panel
{
    /**
     * Captured payload hydrated by {@see hydrate()}, or `null` before hydration.
     */
    private EventSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the events grid.
     *
     * @return string Rendered detail view.
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

    /**
     * Returns the panel display name from the shared title enum.
     *
     * @return string Panel display name.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::EVENTS->value;
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     *
     * @return string Toolbar icon key.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::EVENTS->value;
    }

    /**
     * @return bool `true` when the capture holds at least one event; `false` otherwise.
     */
    public function hasEvents(): bool
    {
        return $this->getEvents() !== [];
    }

    /**
     * Decodes the captured payload into the typed event snapshot backing this panel.
     *
     * @param array<string, mixed> $payload Captured panel payload.
     *
     * @throws HydrationException when the payload does not match the snapshot schema.
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = EventSnapshot::fromArray(
            $payload,
            "$.panels.{$this->id}",
        );
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
