<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use yii\base\BaseObject;

/**
 * Stand-in for a component exposing its dispatcher through a Yii getter/setter pair instead of a public property.
 */
final class SetterComponent extends BaseObject
{
    /**
     * Dispatcher stored behind the Yii property accessors, or `null` while none is configured.
     */
    private EventDispatcherInterface|null $dispatcher = null;

    public function getEventDispatcher(): EventDispatcherInterface|null
    {
        return $this->dispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface|null $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }
}
