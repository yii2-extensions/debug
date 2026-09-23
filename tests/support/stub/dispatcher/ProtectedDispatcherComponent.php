<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Stand-in for a component keeping its `eventDispatcher` property protected, so configuration cannot write it.
 */
class ProtectedDispatcherComponent
{
    /**
     * Dispatcher a subclass owns, never exposed to configuration.
     */
    protected EventDispatcherInterface|null $eventDispatcher = null;
}
