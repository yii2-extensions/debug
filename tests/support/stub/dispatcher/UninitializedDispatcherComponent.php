<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Stand-in for a component whose typed public `eventDispatcher` property stays uninitialized when the instance is
 * built without its constructor.
 */
final class UninitializedDispatcherComponent
{
    public function __construct(public EventDispatcherInterface $eventDispatcher) {}
}
