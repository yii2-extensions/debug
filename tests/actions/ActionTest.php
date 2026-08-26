<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\actions\Action;
use yii\debug\exception\Message;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for the shared standalone debugger action contract.
 */
#[Group('actions')]
final class ActionTest extends TestCase
{
    public function testGetDebugModuleRejectsActionWithoutDebugModule(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(Message::DEBUG_ACTION_MODULE_INVALID->getMessage());

        (new Action('orphan'))->getDebugModule();
    }
}
