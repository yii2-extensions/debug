<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use yii\base\BaseObject;

/**
 * Stand-in for a component whose public readonly `eventDispatcher` property shadows its Yii accessors, so an
 * assignment never reaches the setter.
 */
final class ReadonlyAccessorComponent extends BaseObject
{
    /**
     * Dispatcher fixed at construction.
     */
    public readonly EventDispatcherInterface|null $eventDispatcher;

    public function __construct(EventDispatcherInterface|null $dispatcher = null)
    {
        $this->eventDispatcher = $dispatcher;

        parent::__construct();
    }

    public function getEventDispatcher(): EventDispatcherInterface|null
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface|null $eventDispatcher): void {}
}
