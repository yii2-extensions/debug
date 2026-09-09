<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\actions\PhpInfoAction;
use yii\debug\tests\support\ActionTestCase;

/**
 * Unit tests for {@see PhpInfoAction} covering `run` rendering of the PHP information view.
 */
#[Group('actions')]
final class PhpInfoActionTest extends ActionTestCase
{
    public function testActionPhpInfoRendersPhpInfoView(): void
    {
        $module = $this->bootDebugModule();

        $html = $this->runDebugAction(
            new PhpInfoAction('php-info'),
            $module,
        );

        self::assertNotSame(
            '',
            $html,
            "'phpinfo' view must render markup.",
        );
    }
}
