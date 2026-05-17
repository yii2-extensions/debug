<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\Event;
use yii\debug\models\search\EventSearch;
use yii\debug\Panel;

use function count;
use function get_class;
use function is_array;
use function is_object;
use function is_string;

/**
 * Captures every framework event triggered during the request and renders them in the Events panel.
 *
 * Subscribes to the wildcard `Event::on('*', '*', …)` listener at {@see init()} time and records each fired event's
 * name, class, sender, and capture timestamp.
 *
 * @extends Panel<array<int, array{
 *   time?: float,
 *   name?: string,
 *   class?: class-string,
 *   isStatic?: string,
 *   senderClass?: string,
 * }>>
 */
class EventPanel extends Panel
{
    /**
     * @var array<int, array{
     *   time: float,
     *   name: string,
     *   class: class-string<Event>,
     *   isStatic: string,
     *   senderClass: string
     * }> Events captured for the current request, in fire order.
     */
    private array $events = [];

    /**
     * Renders the detail view with the events grid.
     */
    public function getDetail(): string
    {
        $searchModel = new EventSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->get(), self::normalizeEvents($this->data));

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
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Events';
    }

    /**
     * Renders the toolbar summary chip with the total event count.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/event/summary',
            [
                'eventCount' => count(self::normalizeEvents($this->data)),
                'panel' => $this,
            ],
            $this,
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'events';
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
                $eventData = [
                    'class' => get_class($event),
                    'isStatic' => is_object($event->sender) ? '0' : '1',
                    'name' => $event->name,
                    'senderClass' => is_object($event->sender) ? get_class($event->sender) : (string) $event->sender,
                    'time' => microtime(true),
                ];

                $this->events[] = $eventData;
            },
        );
    }

    /**
     * Snapshots the captured events into the panel-data shape consumed by the detail view.
     *
     * @return array<int, array{
     *   time: float,
     *   name: string,
     *   class: class-string<Event>,
     *   isStatic: string,
     *   senderClass: string
     * }> Event records in fire order.
     */
    public function save(): array
    {
        return $this->events;
    }

    /**
     * Returns the toolbar item showing the total event count, or `null` when none were captured.
     *
     * @return array<int, array<string, mixed>>|null Single-element list with the count, or `null`.
     */
    protected function getToolbarItems(): array|null
    {
        $eventCount = count(self::normalizeEvents($this->data));

        if ($eventCount === 0) {
            return null;
        }

        return [['value' => $eventCount]];
    }

    /**
     * Narrows the saved event rows into a string-keyed list, dropping non-array entries and non-string keys inside
     * each entry.
     *
     * @param mixed $events Raw event rows loaded from saved panel data.
     *
     * @return array<int, array<string, mixed>> Sanitized event records in original order.
     */
    private static function normalizeEvents(mixed $events): array
    {
        if (!is_array($events)) {
            return [];
        }

        $normalized = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $row = [];

            foreach ($event as $key => $value) {
                if (is_string($key)) {
                    $row[$key] = $value;
                }
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
