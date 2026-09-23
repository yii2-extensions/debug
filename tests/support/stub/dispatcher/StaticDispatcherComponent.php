<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Stand-in for a component whose `eventDispatcher` property is static, so no instance can carry its own dispatcher.
 */
final class StaticDispatcherComponent
{
    /**
     * Class-wide dispatcher, never set by the attacher.
     */
    public static EventDispatcherInterface|null $eventDispatcher = null;
}
