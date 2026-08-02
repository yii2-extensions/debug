<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use Yii;
use yii\base\Event;
use yii\debug\models\search\EventSearch;
use yii\debug\Panel;
use yii\debug\panels\event\{EventRow, EventSnapshot};

use function count;
use function is_object;

/**
 * Captures every framework event triggered during the request and renders them in the Events panel.
 *
 * Subscribes to the wildcard `Event::on('*', '*', …)` listener at {@see init()} time and records each fired event's
 * name, class, sender, and capture timestamp.
 */
class EventPanel extends Panel
{
    protected const string ICON = 'events';
    protected const string NAME = 'Events';

    /**
     * @var list<EventRow> Events captured for the current request, in fire order.
     */
    private array $events = [];
    private EventSnapshot|null $snapshot = null;

    /**
     * Returns the captured events as a typed snapshot.
     */
    public function capture(): EventSnapshot
    {
        return new EventSnapshot($this->events);
    }

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
     * Registers the wildcard event listener that records every fired event into {@see $events}.
     */
    public function init(): void
    {
        parent::init();

        Event::on(
            '*',
            '*',
            function (Event $event): void {
                $this->events[] = new EventRow(
                    time: microtime(true),
                    name: $event->name,
                    class: $event::class,
                    isStatic: is_object($event->sender) ? '0' : '1',
                    senderClass: is_object($event->sender) ? $event->sender::class : (string) $event->sender,
                );
            },
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
