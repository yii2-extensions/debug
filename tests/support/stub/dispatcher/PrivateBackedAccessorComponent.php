<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use yii\base\BaseObject;

/**
 * Stand-in for a component backing its Yii `eventDispatcher` accessors with a private property of the same name.
 */
final class PrivateBackedAccessorComponent extends BaseObject
{
    /**
     * Dispatcher stored behind the Yii property accessors, or `null` while none is configured.
     */
    private EventDispatcherInterface|null $eventDispatcher = null;

    public function getEventDispatcher(): EventDispatcherInterface|null
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface|null $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
