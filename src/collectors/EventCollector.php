<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use yii\base\Event;

use function is_object;
use function microtime;

/**
 * Captures every framework event triggered during the request for the Events panel.
 */
class EventCollector extends Collector
{
    /**
     * @var list<EventRow> Events captured for the current request, in fire order.
     */
    private array $events = [];
    /**
     * @var (Closure(Event): void)|null Active wildcard listener, kept so {@see stop()} can detach it.
     */
    private Closure|null $listener = null;

    /**
     * Returns the captured events as a typed snapshot.
     *
     * @return EventSnapshot|null Captured event payload; `null` when the collector never started.
     */
    public function capture(): EventSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        return new EventSnapshot($this->events);
    }

    /**
     * Returns the stable ID pairing this collector with the Events panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'event';
    }

    /**
     * Registers the wildcard event listener that records every fired event into {@see $events}.
     */
    protected function start(): void
    {
        $this->events = [];
        $this->listener = function (Event $event): void {
            $this->events[] = new EventRow(
                time: microtime(true),
                name: $event->name,
                class: $event::class,
                isStatic: is_object($event->sender) ? '0' : '1',
                senderClass: is_object($event->sender) ? $event->sender::class : (string) $event->sender,
            );
        };

        Event::on('*', '*', $this->listener);
    }

    /**
     * Detaches the wildcard listener and clears the accumulated events, so a reused worker process starts clean.
     */
    protected function stop(): void
    {
        if ($this->listener !== null) {
            Event::off('*', '*', $this->listener);

            $this->listener = null;
        }

        $this->events = [];
    }
}
