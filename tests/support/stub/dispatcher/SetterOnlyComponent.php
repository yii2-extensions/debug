<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use yii\base\BaseObject;

/**
 * Stand-in for a component declaring a dispatcher setter without the getter the attacher reads it back with.
 */
final class SetterOnlyComponent extends BaseObject
{
    public function setEventDispatcher(EventDispatcherInterface|null $dispatcher): void {}
}
