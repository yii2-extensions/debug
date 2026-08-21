<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPForge\Debug\Helper\Icon;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use RuntimeException;
use stdClass;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{Action, ActionEvent, Application, Controller, Event, InvalidConfigException};
use yii\caching\FileCache;
use yii\db\Connection;
use yii\debug\actions\{PhpInfoAction, ToolbarDataAction};
use yii\debug\{DebugAsset, LogTarget, Module, Panel, ToolbarAsset, ToolbarRenderer, VersionResolver};
use yii\debug\panels\{DbPanel, LogPanel};
use yii\debug\tests\provider\ModuleProvider;
use yii\debug\tests\support\stub\{
    CustomCollector,
    CustomDbPanel,
    CustomUrlRule,
    ModuleBoundRecordingPanel,
    NotALogTarget,
};
use yii\debug\tests\support\TestCase;
use yii\log\{Dispatcher, Target as LogTargetBase};
use yii\web\{AssetManager, ErrorHandler, ErrorHandlerRenderEvent, ForbiddenHttpException, Response, UrlRule, View};

use function array_keys;
use function base64_encode;
use function is_array;
use function is_string;

/**
 * Unit tests for {@see Module} covering IP-based access control, toolbar HTML/JSON rendering, the `php-info` standalone
 * action wiring, debug-asset registration, and request-cache behavior.
 *
 * {@see ModuleProvider} for test case data providers.
 */
#[Group('module')]
final class ModuleTest extends TestCase
{
    public function testActionPhpInfoIsCallableStandalone(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $app = Yii::$app;

        $app->setModule('debug', $module);
        $module->bootstrap($app);

        $assetBasePath = Yii::getAlias('@runtime/assets');

        if (!is_dir($assetBasePath) && !mkdir($assetBasePath, 0o755, true) && !is_dir($assetBasePath)) {
            self::fail(
                "Could not create asset base path: {$assetBasePath}",
            );
        }

        $app->set(
            'assetManager',
            [
                'class' => AssetManager::class,
                'basePath' => $assetBasePath,
                'baseUrl' => '/assets',
            ],
        );

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        $output = $action->runWithParams([]);

        self::assertIsString(
            $output,
            'Rendered output must be a string.',
        );
        self::assertStringContainsString(
            'phpinfo',
            $output,
            "'phpinfo' view must include the heading literal.",
        );
    }

    public function testBeforeActionDisablesLogTargetsWhenEnableDebugLogsFalse(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->enableDebugLogs = false;

        Yii::$app->setModule('debug', $module);

        $fakeTarget = new class extends LogTargetBase {
            public function export(): void {}
        };

        $fakeTarget->enabled = true;

        $dispatcher = new Dispatcher(['targets' => ['file' => $fakeTarget]]);

        $module->set('log', $dispatcher);

        Yii::$app->assetManager->bundles = ['app' => ['sourcePath' => '@app/assets']];
        Yii::$app->view->on(View::EVENT_END_BODY, [$module, 'renderToolbar']);
        Yii::$app->response->on(Response::EVENT_AFTER_PREPARE, [$module, 'setDebugHeaders']);

        $action = new Action('index', new Controller('default', $module));

        self::assertTrue(
            $module->beforeAction($action),
            "'beforeAction' must succeed when access is allowed.",
        );
        self::assertFalse(
            $fakeTarget->enabled,
            "'beforeAction' must disable log targets when 'enableDebugLogs' is false.",
        );
        self::assertSame([], Yii::$app->assetManager->bundles, 'Allowed debugger actions must reset asset bundles.');
        self::assertFalse(
            Yii::$app->view->off(View::EVENT_END_BODY, [$module, 'renderToolbar']),
            'Debugger actions must detach the toolbar listener before rendering.',
        );
        self::assertFalse(
            Yii::$app->response->off(Response::EVENT_AFTER_PREPARE, [$module, 'setDebugHeaders']),
            'Debugger actions must detach the debug-header listener before rendering.',
        );
    }

    public function testBeforeActionReturnsFalseForToolbarDataRouteUnderAccessDenial(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule('debug', $module);

        $action = new Action('toolbar-data', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        self::assertFalse(
            $module->beforeAction($action),
            "Denied access to the 'toolbar-data' action must return 'false' instead of throwing.",
        );
    }

    public function testBeforeActionReturnsFalseWhenParentVetoesAction(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

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
            "When 'parent::beforeAction' yields false the module must abort with 'false'.",
        );
    }

    public function testBeforeActionSkipsUnresolvedLogTargetConfigurations(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->enableDebugLogs = false;

        Yii::$app->setModule('debug', $module);

        $fakeTarget = new class extends LogTargetBase {
            public function export(): void {}
        };

        $fakeTarget->enabled = true;

        $dispatcher = new Dispatcher(['targets' => ['file' => $fakeTarget]]);

        $dispatcher->targets['pending'] = ['class' => LogTargetBase::class];

        $module->set('log', $dispatcher);

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

    public function testBootstrapAppliesAccessChecksToStandaloneDebuggerRequests(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $action = new ToolbarDataAction('toolbar-data');

        $action->setModule($module);

        $event = new ActionEvent($action);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, $event);

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
            "EVENT_BEFORE_REQUEST closure must attach 'setDebugHeaders' to the response.",
        );

        // Trigger the EVENT_BEFORE_ACTION closure → registers `renderToolbar` on the view.
        $event = new ActionEvent(new Action('view', new Controller('default', $module)));

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, $event);

        self::assertTrue(
            Yii::$app->getView()->hasEventHandlers(View::EVENT_END_BODY),
            "EVENT_BEFORE_ACTION closure must attach 'renderToolbar' to the view.",
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

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, new ActionEvent($action));

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

        Yii::$app->setModule('debug', $module);
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

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, $event);

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

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        $event = new ActionEvent($action);

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, $event);

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

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $childModule = new \yii\base\Module('child', $module);
        $action = new PhpInfoAction('php-info');

        $action->setModule($childModule);

        $event = new ActionEvent($action);

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, $event);

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

    public function testCheckAccessAppliesCallbackBeforeGrantingAccess(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->disableCallbackRestrictionWarning = true;
        $module->checkAccessCallback = static fn(): bool => false;

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            "'checkAccessCallback' returning anything other than 'true' must deny access.",
        );
    }

    public function testCheckAccessEmitsWarningWhenCallbackDeniesWithWarningEnabled(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->disableCallbackRestrictionWarning = false;
        $module->checkAccessCallback = static fn(): bool => false;

        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
        Yii::getLogger()->messages = [];

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            "Callback denying access must return 'false'.",
        );

        self::assertStringContainsString(
            'Access to debugger is denied due to checkAccessCallback.',
            $this->collectLoggedMessages(),
            "A 'Yii::warning' must surface the callback-denial reason when the warning flag is enabled.",
        );
    }

    public function testCheckAccessGrantsWhenCallbackApproves(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];
        $module->checkAccessCallback = static fn(): bool => true;

        self::assertTrue(
            $this->invoke($module, 'checkAccess'),
            "'checkAccessCallback' returning 'true' must grant access after the IP filter passes.",
        );
    }

    /**
     * @param list<string> $allowedIPs
     */
    #[DataProviderExternal(ModuleProvider::class, 'checkAccessCases')]
    public function testCheckAccessHonorsAllowedIpAndCidrFilters(
        array $allowedIPs,
        string $userIp,
        bool $expectedResult,
    ): void {
        $module = new Module('debug');

        $module->allowedIPs = $allowedIPs;

        $_SERVER['REMOTE_ADDR'] = $userIp;

        self::assertSame(
            $expectedResult,
            $this->invoke(
                $module,
                'checkAccess',
            ),
            'Allowed IP filters must accept matches and reject non-matching addresses.',
        );
    }

    public function testCheckAccessLogsExactIpRestrictionWarning(): void
    {
        $module = new Module('debug');
        $module->allowedIPs = ['10.0.0.1'];

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
        Yii::getLogger()->messages = [];

        self::assertFalse(
            $this->invoke($module, 'checkAccess'),
            'Unlisted IP must be rejected.',
        );
        self::assertSame(
            'Access to debugger is denied due to IP address restriction. The requesting IP address is 127.0.0.1',
            $this->collectLoggedMessages(),
            'Denied IP access must emit the exact warning.',
        );
    }

    public function testCheckAccessMatchesAllowedHostsViaDnsResolution(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = [];
        $module->allowedHosts = ['localhost'];

        $_SERVER['REMOTE_ADDR'] = gethostbyname('localhost');

        self::assertTrue(
            $this->invoke($module, 'checkAccess'),
            "'allowedHosts' must be resolved via DNS and matched against the requester IP.",
        );
    }

    public function testCoreActionsAndCollectorsExposeEveryBuiltInId(): void
    {
        $module = new Module('debug');

        $coreActions = $this->invoke(
            $module,
            'coreActionMap',
        );
        $coreCollectors = $this->invoke(
            $module,
            'coreCollectors',
        );

        self::assertIsArray(
            $coreActions,
            'Core action map must be an array.',
        );
        self::assertIsArray(
            $coreCollectors,
            'Core collector map must be an array.',
        );

        self::assertSame(
            ['download-mail', 'index', 'php-info', 'reset-identity', 'set-identity', 'toolbar-data', 'view'],
            array_keys($coreActions),
            'Core action map must retain every debugger endpoint.',
        );
        self::assertSame(
            [
                'asset',
                'config',
                'db',
                'dump',
                'event',
                'inertia',
                'log',
                'mail',
                'profiling',
                'queue',
                'request',
                'router',
                'timeline',
                'user',
            ],
            array_keys($coreCollectors),
            'Core collector map must retain every adapter.',
        );
    }

    public function testCorePanelsFollowTheRequestFlowOrder(): void
    {
        $module = new Module('debug');

        $corePanels = $this->invoke(
            $module,
            'corePanels',
        );

        self::assertIsArray(
            $corePanels,
            'Core panel configurations must be an array.',
        );
        self::assertSame(
            [
                'config',
                'request',
                'router',
                'inertia',
                'user',
                'log',
                'db',
                'profiling',
                'timeline',
                'event',
                'mail',
                'queue',
                'dump',
                'asset',
            ],
            array_keys($corePanels),
            'Order: dispatch, then diagnostics, then side effects.',
        );
    }

    public function testCreateStandaloneActionDoesNotTreatNestedMapKeysAsDirectActions(): void
    {
        $module = new Module('debug');
        $module->actionMap['nested/action'] = PhpInfoAction::class;

        self::assertNull(
            $this->invoke($module, 'createStandaloneAction', ['nested/action']),
            'Nested routes must fall through instead of being resolved as direct action IDs.',
        );
    }

    public function testDebugAssetShipsLocalFrameworkAgnosticScript(): void
    {
        $asset = new DebugAsset();

        self::assertSame(
            ['dist/js/debug.min.js'],
            $asset->js,
            'DebugAsset must ship one consolidated panel script.',
        );
        self::assertSame(
            'module',
            $asset->jsOptions['type'] ?? null,
            'Panel script must load as an ES module.',
        );
        self::assertSame(
            Yii::getAlias(Module::SOURCE_PATH),
            $asset->sourcePath,
            'DebugAsset must publish the framework-neutral core frontend.',
        );
    }

    public function testDebugAssetShipsSingleMainStylesheet(): void
    {
        $asset = new DebugAsset();

        self::assertSame(
            ['dist/css/debug.min.css'],
            $asset->css,
            'DebugAsset must ship one consolidated stylesheet.',
        );
    }

    public function testDebugAssetsShipSharedFocusRuntime(): void
    {
        self::assertFileExists(
            Yii::getAlias(Module::SOURCE_PATH) . '/dist/js/focus.min.js',
            'Published shared assets must include the toolbar keyboard-focus runtime.',
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

        Yii::$app->setModule('debug', $module);

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

        self::assertSame('', $toolbar, 'A debugger page must not render a nested toolbar.');

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        self::assertFalse(
            $response->getHeaders()->has('X-Debug-Tag'),
            'A debugger response must not emit debug headers.',
        );
    }

    public function testDefaultVersionFallsBackToInstalledExtensionVersion(): void
    {
        Yii::$app->extensions['yiisoft/yii2-debug'] = [
            'name' => 'yiisoft/yii2-debug',
            'version' => '2.0.7',
        ];

        $module = new Module('debug');

        self::assertSame(
            VersionResolver::forPackage('yii2-extensions/debug'),
            $module->getVersion(),
            'Module version must resolve from Composer package metadata.',
        );
    }

    public function testGetToolbarHtmlBuildsSkipAjaxRequestUrlEntries(): void
    {
        $module = new Module('debug');

        $module->skipAjaxRequestUrl = [
            'ping' => ['/healthcheck'],
            'route-string' => 'site/index',
            0 => 'numeric-key-ignored',
        ];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $html = $module->getToolbarHtml();

        self::assertStringContainsString(
            'data-skip-urls',
            $html,
            "'skipAjaxRequestUrl' entries must surface in the 'data-skip-urls' attribute on the toolbar element.",
        );
    }

    public function testGetToolbarHtmlEmitsCustomElementWithDataUrlAndDefaults(): void
    {
        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $html = $module->getToolbarHtml();

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Bootstrap must coerce logTarget to a LogTarget instance.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $html,
            'Toolbar must render the custom element marker.',
        );
        self::assertStringContainsString(
            "data-url=\"/index.php?r=debug%2Ftoolbar-data&amp;tag={$logTarget->tag}\"",
            $html,
            'Toolbar must point its data-url to the toolbar-data action with the current tag.',
        );
        self::assertStringContainsString(
            'data-position="bottom"',
            $html,
            'Default position must be bottom.',
        );
        self::assertStringContainsString(
            'data-height="50"',
            $html,
            "Default height percentage must be '50'.",
        );
    }

    public function testGetYiiLogoUsesSharedFrontendAsset(): void
    {
        self::assertSame(
            'data:image/svg+xml;base64,' . base64_encode(Icon::render('yii')),
            Module::getYiiLogo(),
            "'getYiiLogo()' must use the shared Yii logo file by default.",
        );
    }

    public function testHtmlTitleResolvesCallableTitle(): void
    {
        $module = new Module('debug');

        Yii::$app->request->setHostInfo('https://debug.example');
        Yii::$app->request->setBaseUrl('/app');

        $module->pageTitle = static fn(string $base): string => "Title for {$base}";

        self::assertSame(
            'Title for https://debug.example/app',
            $module->htmlTitle(),
            "Callable 'pageTitle' must receive the absolute base URL.",
        );
    }

    public function testHtmlTitleUsesLiteralStringWhenSet(): void
    {
        $module = new Module('debug');

        $module->pageTitle = 'My Debug';

        self::assertSame(
            'My Debug',
            $module->htmlTitle(),
            "String 'pageTitle' must surface verbatim from 'htmlTitle()'.",
        );
    }

    public function testInitConfiguresSharedViewAlias(): void
    {
        Yii::setAlias(Module::VIEW_PATH_ALIAS, '@runtime/not-debug-core');

        $module = new Module('debug');

        self::assertSame(
            Yii::getAlias($module->viewPath),
            Yii::getAlias(Module::VIEW_PATH_ALIAS),
            'The adapter-owned alias must target the shared Debug Core templates.',
        );
    }

    public function testInitDoesNotRegisterTheGenericPanelBaseClass(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        self::assertFalse(
            $module->has(Panel::class),
            'Generic base must stay unregistered.',
        );
    }

    public function testInitPanelsAcceptsArrayConfigWithExtraProperties(): void
    {
        $module = new Module('debug', null, ['panels' => ['log' => ['class' => LogPanel::class]]]);

        self::assertArrayHasKey(
            'log',
            $module->panels,
            "'log' panel must surface after array-config resolution.",
        );
        self::assertInstanceOf(
            LogPanel::class,
            $module->panels['log'],
            "Array-shaped panel config with 'class' key must be resolved through the container.",
        );
    }

    public function testInitPanelsAcceptsPanelInstanceVerbatim(): void
    {
        $existing = new LogPanel();
        $module = new Module('debug', null, ['panels' => ['log-instance' => $existing]]);

        self::assertArrayHasKey('log-instance', $module->panels, 'Panel-instance config must surface under its id.');
        self::assertSame(
            $existing,
            $module->panels['log-instance'],
            "Panel instances passed in config must be reused verbatim with 'id' bound.",
        );
        self::assertSame(
            'log-instance',
            $existing->id,
            "'buildPanel()' must bind the panel id onto an already-instantiated panel.",
        );
    }

    public function testInitPanelsAcceptsStringPanelClass(): void
    {
        $module = new Module('debug', null, ['panels' => ['log-string' => LogPanel::class]]);

        self::assertArrayHasKey(
            'log-string',
            $module->panels,
            "Panel config given as a class-name string must be instantiated through 'buildPanel()'.",
        );
        self::assertInstanceOf(
            LogPanel::class,
            $module->panels['log-string'],
            "String-built panel must be a 'LogPanel' instance.",
        );
    }

    public function testInitPanelsContinuesAfterAnInvalidCustomPanel(): void
    {
        $valid = new LogPanel();
        $module = new Module(
            'debug',
            null,
            ['panels' => ['broken' => ['class' => 'No\\Such\\Class'], 'after-broken' => $valid]],
        );

        self::assertSame(
            $valid,
            $module->panels['after-broken'] ?? null,
            'An invalid panel must not prevent later configured panels from loading.',
        );
    }

    public function testInitPanelsDropsDisabledPanels(): void
    {
        $disabled = new class extends Panel {
            public function isEnabled(): bool
            {
                return false;
            }
        };

        $module = new Module('debug', null, ['panels' => ['ghost' => $disabled]]);

        self::assertArrayNotHasKey(
            'ghost',
            $module->panels,
            "Panels whose 'isEnabled()' returns false must be removed during 'initPanels()'.",
        );
    }

    public function testInitPanelsFiresModuleBoundOnceForEveryResolutionPath(): void
    {
        $instance = new ModuleBoundRecordingPanel();

        $module = new Module(
            'debug',
            null,
            [
                'panels' => [
                    'rec-array' => ['class' => ModuleBoundRecordingPanel::class],
                    'rec-instance' => $instance,
                ],
            ],
        );

        self::assertSame(
            1,
            $instance->moduleBoundCalls,
            'Exactly one invocation on the instance path.',
        );
        self::assertTrue(
            $instance->moduleBoundWithModule,
            'Module reference must be present at call time.',
        );

        self::assertArrayHasKey(
            'rec-array',
            $module->panels,
            'Array-built panel must surface under its id.',
        );

        $arrayPanel = $module->panels['rec-array'];

        self::assertInstanceOf(
            ModuleBoundRecordingPanel::class,
            $arrayPanel,
            'Array-built panel must resolve through the container.',
        );
        self::assertSame(
            1,
            $arrayPanel->moduleBoundCalls,
            'Exactly one invocation on the array path.',
        );
        self::assertTrue(
            $arrayPanel->moduleBoundWithModule,
            'Module reference must be present at call time.',
        );
    }

    public function testInitPanelsOverridesCorePanelByMatchingId(): void
    {
        $override = new LogPanel();
        $module = new Module('debug', null, ['panels' => ['log' => $override]]);

        self::assertArrayHasKey(
            'log',
            $module->panels,
            'Override entry must surface under the same id.',
        );
        self::assertSame(
            $override,
            $module->panels['log'],
            'Custom panel with the same id as a core panel must replace the core entry, exercising the '
            . "'unset(\$corePanels[\$id])' branch.",
        );
    }

    public function testInitPanelsReturnsNullWhenConfigClassIsInvalid(): void
    {
        $module = new Module('debug', null, ['panels' => ['broken' => ['class' => 'No\\Such\\Class']]]);

        self::assertArrayNotHasKey(
            'broken',
            $module->panels,
            'Panel configs with an unloadable class must be dropped silently.',
        );
    }

    public function testInitRegistersEnabledPanelsInServiceLocatorUnderTheirClass(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'db' => [
                        'class' => Connection::class,
                        'dsn' => 'sqlite::memory:',
                    ],
                ],
            ],
        );

        $module = new Module('debug');

        self::assertTrue(
            $module->has(DbPanel::class),
            'DB panel must be locatable by its class.',
        );
        self::assertSame(
            $module->panels['db'] ?? null,
            $module->get(DbPanel::class),
            'Locator must return the registered panel instance.',
        );
    }

    public function testInitRegistersPanelAncestorClassesBelowThePanelBase(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'db' => [
                        'class' => Connection::class,
                        'dsn' => 'sqlite::memory:',
                    ],
                ],
            ],
        );

        $module = new Module('debug', null, ['panels' => ['db' => CustomDbPanel::class]]);

        self::assertSame(
            $module->panels['db'] ?? null,
            $module->get(CustomDbPanel::class),
            'Subclass key must resolve to the configured instance.',
        );
        self::assertSame(
            $module->panels['db'],
            $module->get(DbPanel::class),
            'Built-in class key must resolve to the same subclass instance.',
        );
    }

    public function testInitSkipsServiceRegistrationForDisabledPanels(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        self::assertFalse(
            $module->has(DbPanel::class),
            'Disabled panel must not be locatable.',
        );
    }

    public function testInjectToolbarOnErrorPageAppendsWhenBodyMarkerMissing(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $event = new ErrorHandlerRenderEvent();

        $event->output = 'plain text error';

        $module->injectToolbarOnErrorPage($event);

        self::assertStringContainsString(
            'plain text error',
            $event->output,
            'Original output must be preserved.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $event->output,
            "'injectToolbarOnErrorPage' must append the toolbar when no closing body marker is present.",
        );
    }

    public function testInjectToolbarOnErrorPageReplacesClosingBodyMarker(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $event = new ErrorHandlerRenderEvent();

        $event->output = '<html><body>boom</body></html>';

        $module->injectToolbarOnErrorPage($event);

        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $event->output,
            "'injectToolbarOnErrorPage' must inject the toolbar HTML before '</body>'.",
        );
        self::assertStringContainsString(
            '<script type="module"',
            $event->output,
            'Runtime script must load as an ES module.',
        );
        self::assertTrue(
            strpos($event->output, '<yii-debug-toolbar') < strpos($event->output, '<script type="module"'),
            'Toolbar markup must precede its module script.',
        );
    }

    public function testInjectToolbarOnErrorPageShortCircuitsOnAjaxRequests(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $module->bootstrap(Yii::$app);

        $event = new ErrorHandlerRenderEvent();

        $event->output = '<body></body>';

        $module->injectToolbarOnErrorPage($event);

        self::assertSame(
            '<body></body>',
            $event->output,
            'AJAX requests must leave the rendered error page untouched.',
        );

        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testLogTargetObjectIsAcceptedAsConfig(): void
    {
        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $module->bootstrap(Yii::$app);

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Object-typed logTarget must be retained verbatim.',
        );
    }

    public function testModuleExtensionPointsRemainProtected(): void
    {
        foreach (
            [
                'checkAccess',
                'coreActionMap',
                'coreCollectors',
                'corePanels',
                'initCollectors',
                'initPanels',
                'initPanelServices',
                'resetGlobalSettings',
            ] as $method
        ) {
            self::assertTrue(
                (new \ReflectionMethod(Module::class, $method))->isProtected(),
                "Module::{$method}() must remain available to subclasses.",
            );
        }
    }

    public function testModuleInitRetainsYiiNamespaceDefaults(): void
    {
        $module = new Module('debug');

        self::assertSame(
            'yii\\debug\\controllers',
            $module->controllerNamespace,
            'Module::controllerNamespace must default to the Yii debug controllers namespace.'
        );
        self::assertSame(
            'yii\\debug\\controllers',
            $module->actionNamespace,
            'Module::actionNamespace must default to the Yii debug controllers namespace.'
        );
    }

    public function testRenderToolbarHonorsCustomModuleId(): void
    {
        $moduleId = 'my_debug';

        $module = new Module($moduleId);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule($moduleId, $module);

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        ob_start();

        $module->renderToolbar(new Event(['sender' => Yii::$app->view]));
        $output = (string) ob_get_clean();

        self::assertThat(
            $output,
            self::logicalOr(
                self::matches('%Adata-url="/my_debug%A'),
                self::matches('%Adata-url="/index.php?r=my_debug%A'),
            ),
            'Toolbar URL must include the custom module id regardless of the URL manager strategy.',
        );
    }

    public function testRenderToolbarMarkupVariesByTagAcrossCachedRequests(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        Yii::$app->set(
            'cache',
            new FileCache(['cachePath' => '@runtime/cache']),
        );

        $view = Yii::$app->view;

        $output = ['', ''];

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Bootstrap must coerce logTarget to a LogTarget instance.',
        );

        for ($i = 0; $i <= 1; $i++) {
            ob_start();

            $logTarget->tag = "tag{$i}";

            if ($view->beginCache(__FUNCTION__, ['duration' => 3])) {
                $module->renderToolbar(new Event(['sender' => $view]));
                $view->endCache();
            }

            $output[$i] = (string) ob_get_clean();
        }

        self::assertNotSame(
            $output[0],
            $output[1],
            'Toolbar render must reflect the current tag despite ViewCache wrapping.',
        );
    }

    public function testRenderToolbarSkipsWhenAccessDenied(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.0'];
        $module->disableIpRestrictionWarning = true;

        $module->bootstrap(Yii::$app);

        ob_start();
        $module->renderToolbar(new Event(['sender' => Yii::$app->view]));
        $output = (string) ob_get_clean();

        self::assertSame(
            '',
            $output,
            'Access denial must short-circuit the toolbar render.',
        );
    }

    public function testRenderToolbarSkipsWhenSenderIsNotAView(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        ob_start();
        $module->renderToolbar(new Event(['sender' => new stdClass()]));
        $output = (string) ob_get_clean();

        self::assertSame(
            '',
            $output,
            'Non-View senders must short-circuit the toolbar render.',
        );
    }

    public function testResolveLogTargetAcceptsArrayConfigWithExtraProperties(): void
    {
        $module = new Module('debug');
        $module->logTarget = ['class' => LogTarget::class, 'levels' => 7];

        $module->bootstrap(Yii::$app);

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            "Array config with 'class' key must be resolved via the container.",
        );
        self::assertSame(
            7,
            $module->logTarget->levels,
            "Extra properties in the array config must be applied to the resolved 'LogTarget'.",
        );
    }

    public function testResolveLogTargetAcceptsStringClassName(): void
    {
        $module = new Module('debug');
        $module->logTarget = LogTarget::class;

        $module->bootstrap(Yii::$app);

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            "String class name in 'logTarget' must be resolved via the container into a 'LogTarget'.",
        );
    }

    public function testSetAndGetYiiLogoRoundTrip(): void
    {
        Module::setYiiLogo('data:image/svg+xml;base64,FAKE');

        self::assertSame(
            'data:image/svg+xml;base64,FAKE',
            Module::getYiiLogo(),
            "'setYiiLogo()' must persist the URI returned by 'getYiiLogo()'.",
        );

        // Reset cache so other tests see the bundled logo path again.
        $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);
    }

    public function testSetDebugHeadersAppliesAllThreeHeaders(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $_SERVER['REQUEST_TIME_FLOAT'] = 1000.0;

        MockerState::addCondition(
            'yii\\debug',
            'microtime',
            [true],
            1001.0,
            true,
        );

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        $headers = $response->getHeaders();

        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Bootstrap must resolve the log target.',
        );
        self::assertSame(
            [
                'tag' => $module->logTarget->tag,
                'duration' => '1,001',
                'link' => \yii\helpers\Url::toRoute(['/debug/view', 'tag' => $module->logTarget->tag]),
            ],
            [
                'tag' => $headers->get('X-Debug-Tag'),
                'duration' => $headers->get('X-Debug-Duration'),
                'link' => $headers->get('X-Debug-Link'),
            ],
            'Debug headers must contain exact tag, duration, and link values.',
        );
    }

    public function testSetDebugHeadersSkipsWhenAccessDenied(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.0'];
        $module->disableIpRestrictionWarning = true;

        $module->bootstrap(Yii::$app);

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        self::assertFalse(
            $response->getHeaders()->has('X-Debug-Tag'),
            'Access denial must skip the debug-header injection.',
        );
    }

    public function testSetDebugHeadersSkipsWhenSenderIsNotResponse(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $module->setDebugHeaders(new Event(['sender' => new stdClass()]));

        self::assertFalse(
            Yii::$app->getResponse()->getHeaders()->has('X-Debug-Tag'),
            'Non-Response senders must leave headers untouched.',
        );
    }

    public function testThrowForbiddenHttpExceptionForRemovedToolbarActionUnderAccessDenial(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule('debug', $module);

        $action = new Action('toolbar', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage(
            'not allowed to access',
        );

        $module->beforeAction($action);
    }

    public function testThrowForbiddenHttpExceptionWhenAccessDeniedOnNonToolbarAction(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.0'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule('debug', $module);

        $action = new Action('view', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage(
            'not allowed to access',
        );

        $module->beforeAction($action);
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetClassDoesNotResolveToLogTarget(): void
    {
        $module = new Module('debug');
        $module->logTarget = NotALogTarget::class;

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'must resolve to a yii\\debug\\LogTarget instance',
        );

        $module->bootstrap(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetConfigDeclaresMissingClass(): void
    {
        $module = new Module('debug');
        $module->logTarget = ['class' => 'No\\Such\\LogTarget'];

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'must declare a valid class name',
        );

        $module->bootstrap(Yii::$app);
    }

    public function testThrowInvalidConfigExceptionWhenToolbarHtmlBuiltBeforeBootstrap(): void
    {
        $module = new Module('debug');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'logTarget has not been bootstrapped',
        );

        $module->getToolbarHtml();
    }

    public function testThrowRuntimeExceptionWhenSharedYiiLogoIsUnavailable(): void
    {
        $this->setInaccessibleStaticProperty(Icon::class, 'cache', ['yii' => '']);
        $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);

        try {
            Module::getYiiLogo();

            self::fail(
                'A missing packaged Yii logo must raise an explicit runtime error.',
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Unable to read the packaged Yii logo.',
                $exception->getMessage(),
                'A missing packaged Yii logo must report the failing asset boundary.',
            );
        } finally {
            $this->setInaccessibleStaticProperty(Icon::class, 'cache', []);
            $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);
        }
    }

    public function testToolbarAssetShipsSharedRuntimeAsEsModule(): void
    {
        $asset = new ToolbarAsset();

        self::assertSame(
            ['dist/js/toolbar.min.js'],
            $asset->js,
            'ToolbarAsset must ship one consolidated runtime script.',
        );
        self::assertSame(
            'module',
            $asset->jsOptions['type'] ?? null,
            'Runtime script must load as an ES module.',
        );
        self::assertSame(
            Yii::getAlias(Module::SOURCE_PATH),
            $asset->sourcePath,
            'ToolbarAsset must publish the framework-neutral core frontend.',
        );
    }

    public function testToolbarDataActionExposesNewBrandKeys(): void
    {
        $this->resetDebugDataPath();

        $module = new Module('debug', null, ['dataPath' => '@runtime/debug']);

        $module->allowedIPs = ['*'];

        $app = Yii::$app;

        $app->setModule('debug', $module);
        $app->getRequest()->setUrl('dummy');
        $module->bootstrap($app);

        Yii::$app->log->getLogger()->messages = [];

        Yii::debug('manifest-bootstrap');

        Yii::$app->log->getLogger()->flush(true);

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Bootstrap must coerce logTarget to a LogTarget instance.',
        );

        $manifest = $logTarget->loadManifest();

        $tag = array_key_first($manifest);

        self::assertIsString(
            $tag,
            'Manifest must expose at least one captured request tag.',
        );

        $action = new ToolbarDataAction('toolbar-data');

        $action->setModule($module);

        $data = $action->run($tag);

        self::assertArrayNotHasKey(
            'error',
            $data,
            'toolbar-data must take the success branch for a known tag.',
        );
        self::assertArrayHasKey(
            'title',
            $data,
            'Success payload must declare the title key.',
        );
        self::assertSame(
            Response::FORMAT_JSON,
            $app->getResponse()->format,
            'toolbar-data must respond as JSON.',
        );
        self::assertSame(
            'Yii Debugger',
            $data['title'],
            'Title must always identify the toolbar.',
        );
        self::assertSame(
            $tag,
            $data['tag'],
            'Returned tag must match the requested tag.',
        );
        self::assertSame(
            'bottom',
            $data['position'],
            'Default position must be bottom.',
        );
        self::assertNotEmpty(
            $data['items'],
            'Toolbar payload must include at least one panel item.',
        );
        self::assertArrayHasKey(
            'id',
            $data['items'][0],
            'Each panel item must carry its registered id.',
        );
        self::assertArrayHasKey(
            'url',
            $data['items'][0],
            'Each panel item must carry a navigable url.',
        );
    }

    public function testToolbarRendererKeepsExplicitView(): void
    {
        $module = new Module('debug');
        $view = new View();

        $renderer = $this->invoke(
            $module,
            'toolbarRenderer',
            [$view],
        );

        self::assertInstanceOf(
            ToolbarRenderer::class,
            $renderer,
            'ToolbarRenderer must be returned from the module.',
        );
        self::assertSame(
            $view,
            $this->getInaccessibleProperty($renderer, 'view'),
            'Explicit render views must not be replaced by the application view.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $assetBasePath = dirname(__DIR__) . '/runtime/assets';

        if (!is_dir($assetBasePath) && !mkdir($assetBasePath, 0o755, true) && !is_dir($assetBasePath)) {
            self::fail(
                "Could not create asset base path: {$assetBasePath}",
            );
        }

        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'basePath' => $assetBasePath,
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );
    }

    /**
     * Concatenates every logged message body for the active logger so PHPStan can narrow the result to `string` without
     * choking on the empty-array seed the test sets before invoking the SUT.
     */
    private function collectLoggedMessages(): string
    {
        $messages = $this->getInaccessibleProperty(Yii::getLogger(), 'messages');

        $out = '';

        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                if (is_array($message) && isset($message[0]) && is_string($message[0])) {
                    $out .= $message[0];
                }
            }
        }

        return $out;
    }

    /**
     * Wipes any stale debug snapshot files left over by previous tests sharing the `@runtime/debug` data path.
     */
    private function resetDebugDataPath(): void
    {
        $path = Yii::getAlias('@runtime/debug');

        if (!is_dir($path)) {
            return;
        }

        $files = glob("{$path}/*");

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Replaces the default log dispatcher with a no-op so toolbar rendering does not flush events.
     */
    private function silenceLogger(): void
    {
        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
    }
}
