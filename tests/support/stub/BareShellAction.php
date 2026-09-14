<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\actions\Action;

/**
 * Stub debugger action that renders nothing, leaving in place the bare shell installed by `beforeRun()`.
 */
final class BareShellAction extends Action
{
    public function run(): string
    {
        return '';
    }
}
