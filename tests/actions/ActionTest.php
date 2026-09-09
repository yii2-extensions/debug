<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\actions\Action;
use yii\debug\actions\ViewAction;
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\tests\support\ActionTestCase;
use yii\web\NotFoundHttpException;

/**
 * Unit tests for {@see Action} covering `getDebugModule` rejection without a debug module, `getLogTarget` rejection
 * before initialization, and `getPanel` rejection for an unregistered panel.
 */
#[Group('actions')]
final class ActionTest extends ActionTestCase
{
    public function testGetDebugModuleRejectsActionWithoutDebugModule(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_ACTION_MODULE_INVALID->getMessage(),
        );

        (new Action('orphan'))->getDebugModule();
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetIsMissing(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        // Skip 'bootstrap()' so 'logTarget' stays as the default config array.
        $action = new ViewAction('view');

        $action->setModule($module);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_NOT_INITIALIZED_FOR_LOADING->getMessage(),
        );

        $this->invoke(
            $action,
            'getLogTarget',
        );
    }

    public function testThrowNotFoundHttpExceptionWhenPanelIsNotRegistered(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            Message::DEBUG_PANEL_NOT_FOUND->getMessage('missing'),
        );

        $this->invoke(
            $action,
            'getPanel',
            ['missing'],
        );
    }
}
