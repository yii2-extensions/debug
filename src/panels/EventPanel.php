<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\Event;
use yii\debug\models\search\Event as EventSearch;
use yii\debug\Panel;

use function count;
use function get_class;
use function is_array;
use function is_object;
use function is_string;

/**
 * Debugger panel that collects and displays information about triggered events.
 *
 * > Note: this panel requires Yii framework version >= 2.0.14 to function and will not appear at lower version.
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
     * }> Current request events
     */
    private array $events = [];

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
        );
    }

    public function getName(): string
    {
        return 'Events';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/event/summary',
            [
                'eventCount' => count(self::normalizeEvents($this->data)),
                'panel' => $this,
            ],
        );
    }

    public function getToolbarIcon(): string
    {
        return 'events';
    }

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
     * @return array<int, array{
     *   time: float,
     *   name: string,
     *   class: class-string<Event>,
     *   isStatic: string,
     *   senderClass: string
     * }>
     */
    public function save(): array
    {
        return $this->events;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems(): array|null
    {
        $eventCount = count(self::normalizeEvents($this->data));

        if ($eventCount === 0) {
            return null;
        }

        return [
            [
                'value' => $eventCount,
            ],
        ];
    }

    /**
     * @param mixed $events Raw event rows loaded from saved panel data.
     *
     * @return array<int, array<string, mixed>>
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
