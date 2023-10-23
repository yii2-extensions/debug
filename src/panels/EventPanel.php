<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

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
 *
 * > Note: this panel requires Yii framework version >= 2.0.14 to function and will not
 *   appear at lower version.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.14
 */
class EventPanel extends Panel
{
    /**
     * @var array current request events.
     */
    private array $_events = [];

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

    public function getName(): string
    {
        return 'Events';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/event/summary', [
            'panel' => $this,
            'eventCount' => count($this->data),
        ]);
    }

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

    public function save(): mixed
    {
        return $this->_events;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled();
    }
}
