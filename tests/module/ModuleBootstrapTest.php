<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, ActionEvent, Application, Controller};
use yii\debug\actions\{PhpInfoAction, ToolbarDataAction};
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\{CustomCollector, CustomUrlRule};
use yii\web\{ErrorHandler, Response, UrlRule, View};

/**
 * Unit tests for {@see Module} covering `bootstrap` URL rules and event listeners, request-state reset, standalone
 * action access checks and vetoes, explicit debug logging, and capture suppression for debugger and child-module requests.
 */
#[Group('module')]
final class ModuleBootstrapTest extends ModuleTestCase
{
    public function testBootstrapAppliesAccessChecksToStandaloneDebuggerRequests(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        $action = new ToolbarDataAction('toolbar-data');

        $action->setModule($module);

        $event = new ActionEvent($action);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Yii::$app->trigger(
            Application::EVENT_BEFORE_ACTION,
            $event,
        );

        self::assertFalse(
            $event->isValid,
            'Standalone debugger actions must not bypass the module access check.',
        );
    }

    public function testBootstrapClosuresWireToolbarAndDebugHeaderListeners(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Bootstrap must resolve the log target.',
        );

        $previousTag = $logTarget->tag;
        $logTarget->messages = [['old', 4, 'application', 0.0, []]];

        // Trigger the EVENT_BEFORE_REQUEST closure → registers `setDebugHeaders` on the response.
        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertNotSame(
            $previousTag,
            $logTarget->tag,
            'Before-request handling must rotate the request tag.',
        );
        self::assertSame(
            [],
            $logTarget->messages,
            'Before-request handling must clear the previous message buffer.',
        );
        self::assertTrue(
            Yii::$app->getResponse()->off(Response::EVENT_AFTER_PREPARE, [$module, 'setDebugHeaders']),
            'Before-request setup must attach the response header listener.',
        );

        // Trigger the EVENT_BEFORE_ACTION closure → registers `renderToolbar` on the view.
        $event = new ActionEvent(new Action('view', new Controller('default', $module)));

        Yii::$app->trigger(
            Application::EVENT_BEFORE_ACTION,
            $event,
        );

        self::assertTrue(
            Yii::$app->getView()->hasEventHandlers(View::EVENT_END_BODY),
            'Before-action setup must attach the toolbar listener.',
        );
        self::assertTrue(
            Yii::$app->errorHandler->off(ErrorHandler::EVENT_AFTER_RENDER, [$module, 'injectToolbarOnErrorPage']),
            'Bootstrap must attach the error-page toolbar injector.',
        );
    }

    public function testBootstrapKeepsExplicitDebugLoggingWithoutRenderingNestedToolbar(): void
    {
        $collector = new CustomCollector();
        $module = new Module('debug', null, ['collectors' => [$collector]]);

        $module->allowedIPs = ['*'];
        $module->enableDebugLogs = true;

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        Yii::$app->trigger(
            Application::EVENT_BEFORE_ACTION,
            new ActionEvent($action),
        );

        try {
            self::assertInstanceOf(
                LogTarget::class,
                $module->logTarget,
                'Bootstrap must resolve the log target.',
            );
            self::assertTrue(
                $module->logTarget->enabled,
                'Explicit debugger logging must keep the log target enabled.',
            );
            self::assertSame(
                0,
                $collector->shutdownCount,
                'Explicit debugger logging must keep collectors active.',
            );
            self::assertFalse(
                Yii::$app->getView()->off(View::EVENT_END_BODY, [$module, 'renderToolbar']),
                'Debugger pages must never receive a nested toolbar.',
            );
        } finally {
            $module->getCollectorCoordinator()->shutdown();
        }
    }

    public function testBootstrapPrependsExactDebuggerUrlRules(): void
    {
        $manager = Yii::$app->urlManager;

        $manager->enablePrettyUrl = true;

        $manager->addRules([['route' => 'sentinel', 'pattern' => 'sentinel']], true);

        $module = new Module('debug');

        $module->urlRuleClass = CustomUrlRule::class;

        $module->bootstrap(Yii::$app);

        $rules = $manager->rules;

        self::assertContainsOnlyInstancesOf(
            UrlRule::class,
            $rules,
            'All URL rules must be instances of UrlRule or its subclasses.',
        );
        self::assertSame(
            [
                [CustomUrlRule::class, 'debug', '#^debug$#u', false, false],
                [CustomUrlRule::class, 'debug/<action>', '#^debug/(?P<a47cc8c92>[\\w\\-]+)$#u', false, false],
                [UrlRule::class, 'sentinel', '#^sentinel$#u', null, null],
            ],
            array_map(
                static fn(UrlRule $rule): array => [
                    $rule::class,
                    $rule->route,
                    $rule->pattern,
                    $rule->normalizer,
                    $rule->suffix,
                ],
                $rules,
            ),
            'Bootstrap must prepend both debugger rules with exact routing options.',
        );
    }

    public function testBootstrapPreservesAnApplicationVetoForStandaloneDebuggerRequests(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );
        Yii::$app->on(
            Application::EVENT_BEFORE_ACTION,
            static function (ActionEvent $event): void {
                $event->isValid = false;
            },
        );

        $module->bootstrap(Yii::$app);

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        $event = new ActionEvent($action);

        Yii::$app->trigger(
            Application::EVENT_BEFORE_ACTION,
            $event,
        );

        self::assertFalse(
            $event->isValid,
            'The debugger lifecycle must not re-enable an action vetoed by the host.',
        );
    }

    public function testBootstrapSuppressesStandaloneDebuggerRequestsBeforeRendering(): void
    {
        $collector = new CustomCollector();

        $module = new Module('debug', null, ['collectors' => [$collector]]);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        $event = new ActionEvent($action);

        Yii::$app->trigger(
            Application::EVENT_BEFORE_ACTION,
            $event,
        );

        self::assertTrue(
            $event->isValid,
            'An allowed standalone debugger action must continue.',
        );
        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Standalone debugger requests must still use the log target.',
        );
        self::assertFalse(
            $module->logTarget->enabled,
            'Debugger requests must not be persisted by default.',
        );
        self::assertSame(
            1,
            $collector->startupCount,
            'Collectors must start at the request boundary.',
        );
        self::assertSame(
            1,
            $collector->shutdownCount,
            'Debugger requests must stop collectors before rendering.',
        );
        self::assertFalse(
            Yii::$app->getView()->off(View::EVENT_END_BODY, [$module, 'renderToolbar']),
            'Debugger pages must not receive a nested toolbar.',
        );
        self::assertFalse(
            Yii::$app->getResponse()->off(Response::EVENT_AFTER_PREPARE, [$module, 'setDebugHeaders']),
            'Debugger responses must not receive debug headers.',
        );
    }

    public function testBootstrapSuppressesStandaloneDebuggerRequestsOwnedByChildModule(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        $childModule = new \yii\base\Module('child', $module);
        $action = new PhpInfoAction('php-info');

        $action->setModule($childModule);

        $event = new ActionEvent($action);

        Yii::$app->trigger(
            Application::EVENT_BEFORE_ACTION,
            $event,
        );

        self::assertTrue(
            $event->isValid,
            'An allowed child-module debugger action must continue.',
        );
        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Child-module debugger requests must still use the parent log target.',
        );
        self::assertFalse(
            $module->logTarget->enabled,
            'Child-module debugger requests must not be persisted.',
        );
        self::assertFalse(
            Yii::$app->getView()->off(View::EVENT_END_BODY, [$module, 'renderToolbar']),
            'Child-module debugger pages must not receive a nested toolbar.',
        );
    }
}
