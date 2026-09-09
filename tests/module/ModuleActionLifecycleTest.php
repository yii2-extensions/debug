<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, ActionEvent, Controller, Event};
use yii\debug\actions\PhpInfoAction;
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;
use yii\log\{Dispatcher, Target as LogTargetBase};
use yii\web\{ErrorHandlerRenderEvent, Response, View};

/**
 * Unit tests for {@see Module} covering `beforeAction` logging suppression and parent vetoes, unresolved log-target
 * configurations, debugger-action detection, and guards against nested response decoration.
 */
#[Group('module')]
final class ModuleActionLifecycleTest extends ModuleTestCase
{
    public function testBeforeActionDisablesLogTargetsWhenEnableDebugLogsFalse(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->enableDebugLogs = false;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $fakeTarget = new class extends LogTargetBase {
            public function export(): void {}
        };

        $fakeTarget->enabled = true;

        $dispatcher = new Dispatcher(['targets' => ['file' => $fakeTarget]]);

        $module->set('log', $dispatcher);

        Yii::$app->assetManager->bundles = ['app' => ['sourcePath' => '@app/assets']];

        Yii::$app->view->on(
            View::EVENT_END_BODY,
            [$module, 'renderToolbar'],
        );
        Yii::$app->response->on(
            Response::EVENT_AFTER_PREPARE,
            [$module, 'setDebugHeaders'],
        );

        $action = new Action('index', new Controller('default', $module));

        self::assertTrue(
            $module->beforeAction($action),
            'Allowed access must continue action execution.',
        );
        self::assertFalse(
            $fakeTarget->enabled,
            'Disabled debug logging must deactivate existing log targets.',
        );
        self::assertSame(
            [],
            Yii::$app->assetManager->bundles,
            'Allowed debugger actions must reset asset bundles.'
        );
        self::assertFalse(
            Yii::$app->view->off(View::EVENT_END_BODY, [$module, 'renderToolbar']),
            'Debugger actions must detach the toolbar listener before rendering.',
        );
        self::assertFalse(
            Yii::$app->response->off(Response::EVENT_AFTER_PREPARE, [$module, 'setDebugHeaders']),
            'Debugger actions must detach the debug-header listener before rendering.',
        );
    }

    public function testBeforeActionReturnsFalseWhenParentVetoesAction(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $action = new Action('index', new Controller('default', $module));

        // `Module::on(beforeAction, …)` lets a listener veto the action by setting `$event->isValid = false`.
        $module->on(
            Module::EVENT_BEFORE_ACTION,
            static function (ActionEvent $event): void {
                $event->isValid = false;
            },
        );

        self::assertFalse(
            $module->beforeAction($action),
            'A parent veto must stop action execution.',
        );
    }

    public function testBeforeActionSkipsUnresolvedLogTargetConfigurations(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->enableDebugLogs = false;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $fakeTarget = new class extends LogTargetBase {
            public function export(): void {}
        };

        $fakeTarget->enabled = true;

        $dispatcher = new Dispatcher(['targets' => ['file' => $fakeTarget]]);

        $dispatcher->targets['pending'] = ['class' => LogTargetBase::class];

        $module->set(
            'log',
            $dispatcher,
        );

        $action = new Action('index', new Controller('default', $module));

        self::assertTrue(
            $module->beforeAction($action),
            'Raw configuration entries must not abort the walk.',
        );
        self::assertFalse(
            $fakeTarget->enabled,
            'Resolved targets must still be disabled.',
        );
        self::assertSame(
            ['class' => LogTargetBase::class],
            $dispatcher->targets['pending'],
            'Unresolved entries must be left untouched.',
        );
    }

    public function testDebuggerActionDetectionRejectsUnrelatedModules(): void
    {
        $module = new Module('debug');

        $unrelated = new \yii\base\Module('unrelated');

        $action = new Action('index', new Controller('default', $unrelated));

        self::assertFalse(
            $this->invoke($module, 'isDebuggerAction', [$action]),
            'Actions owned by unrelated modules must not be classified as debugger actions.',
        );
    }

    public function testDebuggerActionGuardsSuppressAllResponseDecoration(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        Yii::$app->requestedAction = $action;

        $errorEvent = new ErrorHandlerRenderEvent();

        $errorEvent->output = '<html><body>debug failure</body></html>';

        $module->injectToolbarOnErrorPage($errorEvent);

        self::assertSame(
            '<html><body>debug failure</body></html>',
            $errorEvent->output,
            'A debugger error page must not receive a nested toolbar.',
        );

        ob_start();
        $module->renderToolbar(new Event(['sender' => Yii::$app->view]));
        $toolbar = (string) ob_get_clean();

        self::assertSame(
            '',
            $toolbar,
            'A debugger page must not render a nested toolbar.'
        );

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        self::assertFalse(
            $response->getHeaders()->has('X-Debug-Tag'),
            'A debugger response must not emit debug headers.',
        );
    }
}
