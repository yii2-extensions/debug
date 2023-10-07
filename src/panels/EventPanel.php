<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\Event;
use yii\debug\Panel;

use function count;
use function get_class;
use function is_object;
use function microtime;

/**
 * Debugger panel that collects and displays information about triggered events.
 */
class EventPanel extends Panel
{
    /**
     * @var array current request events.
     */
    private array $_events = [];

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        Event::on('*', '*', function ($event) {
            /* @var $event Event */
            $eventData = [
                'time' => microtime(true),
                'name' => $event->name,
                'class' => get_class($event),
                'isStatic' => is_object($event->sender) ? '0' : '1',
                'senderClass' => is_object($event->sender) ? get_class($event->sender) : $event->sender,
            ];

            $this->_events[] = $eventData;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Events';
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/event/summary', [
            'panel' => $this,
            'eventCount' => count($this->data),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetail(): string
    {
        $searchModel = new \yii\debug\models\search\Event();
        $dataProvider = $searchModel->search(Yii::$app->request->get(), $this->data);

        return Yii::$app->view->render('panels/event/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function save(): mixed
    {
        return $this->_events;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return parent::isEnabled();
    }
}
