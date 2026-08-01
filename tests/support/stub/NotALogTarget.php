<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\BaseObject;
use yii\debug\Module;

/**
 * Stub class that is not a log target, used to test the exception thrown when a log target is misconfigured.
 */
final class NotALogTarget extends BaseObject
{
    public function __construct(public Module $module, array $config = [])
    {
        parent::__construct($config);
    }
}
