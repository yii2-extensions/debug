<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\inertia;

use Psr\EventDispatcher\EventDispatcherInterface;
use yii\base\Component;

/**
 * Stand-in for an `inertia` component the Inertia provider attaches to.
 *
 * The packaged `yii\inertia\Manager` adapter is `final`, so a test registers the class relationship on the internal
 * mocker instead of extending it.
 */
final class CompatibleManager extends Component
{
    /**
     * Dispatcher the debugger registers its collector on, or `null` while none is configured.
     */
    public EventDispatcherInterface|null $eventDispatcher = null;
}
