<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Panel\Event\{EventCapture, EventInspection, EventRow, EventSnapshot};
use Throwable;
use yii\base\{ActionEvent, ViewEvent};
use yii\base\Event;

use function is_object;
use function microtime;

/**
 * Captures framework events reaching the global listener during the request for the Events panel.
 */
class EventCollector extends Collector
{
    /**
     * Opt-in capture of selected action/view metadata; event values and rendered output are never dumped.
     */
    public bool $captureContext = false;

    /**
     * Opt-in argument-free trace depth; zero disables capture, and sixteen is the hard maximum.
     */
    public int $traceLimit = 0;

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
            $row = new EventRow(
                time: microtime(true),
                name: $event->name,
                class: $event::class,
                isStatic: is_object($event->sender) ? '0' : '1',
                senderClass: is_object($event->sender) ? $event->sender::class : (string) $event->sender,
            );

            $this->events[] = $this->captureContext || $this->traceLimit > 0
                ? $row->withInspection($this->inspect($event))
                : $row;
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

    /**
     * Captures selected values at this listener's observation point, not final event state.
     */
    private function inspect(Event $event): EventInspection
    {
        $context = [];
        $trace = [];

        $contextStatus = $this->captureContext ? 'unsupported' : 'disabled';
        $traceStatus = $this->traceLimit > 0 ? 'captured' : 'disabled';

        if ($this->captureContext) {
            try {
                if ($event instanceof ActionEvent) {
                    $context = EventCapture::context([
                        'Action ID' => $event->action->id ?? 'Not available',
                        'Controller ID' => $event->action->controller->id ?? 'Not available',
                        'Continue allowed (observed)' => $event->isValid ? 'Yes' : 'No',
                    ]);
                    $contextStatus = 'captured';
                } elseif ($event instanceof ViewEvent) {
                    $context = EventCapture::context([
                        'View file' => $event->viewFile,
                        'Continue allowed (observed)' => $event->isValid ? 'Yes' : 'No',
                    ]);
                    $contextStatus = 'captured';
                }
            } catch (Throwable) {
                $contextStatus = 'failed';
            }
        }

        if ($this->traceLimit > 0) {
            try {
                $trace = EventCapture::trace(
                    debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32),
                    $this->traceLimit,
                    [
                        __FILE__,
                        \Yii::getAlias('@yii/base/Event.php'),
                        \Yii::getAlias('@yii/base/Component.php'),
                    ],
                );
            } catch (Throwable) {
                $traceStatus = 'failed';
            }
        }

        return (new EventInspection())
            ->withContext($context, $contextStatus)
            ->withTrace($trace, $traceStatus);
    }
}
