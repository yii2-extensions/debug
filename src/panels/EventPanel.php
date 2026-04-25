<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\base\Event;
use yii\debug\Panel;

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
     * @var array current request events
     */
    private $_events = [];

    public function getDetail()
    {
        $searchModel = new \yii\debug\models\search\Event();
        $dataProvider = $searchModel->search(Yii::$app->request->get(), $this->data);

        return Yii::$app->view->render('panels/event/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    public function getName()
    {
        return 'Events';
    }

    public function getSummary()
    {
        return Yii::$app->view->render('panels/event/summary', [
            'panel' => $this,
            'eventCount' => count($this->data),
        ]);
    }

    public function getToolbarIcon()
    {
        return 'events';
    }

    public function init()
    {
        parent::init();

        Event::on('*', '*', function ($event) {
            /** @var Event $event */
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

    public function isEnabled()
    {
        $yiiVersion = Yii::getVersion();
        if (!version_compare($yiiVersion, '2.0.14', '>=') && strpos($yiiVersion, '-dev') === false) {
            return false;
        }

        return parent::isEnabled();
    }

    public function save()
    {
        return $this->_events;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems()
    {
        $eventCount = is_array($this->data) ? count($this->data) : 0;

        if ($eventCount === 0) {
            return null;
        }

        return [
            [
                'value' => $eventCount,
            ],
        ];
    }
}
