<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use stdClass;
use Yii;
use yii\base\{Action, ActionEvent, Application, Controller, Event, InvalidConfigException};
use yii\caching\FileCache;
use yii\debug\controllers\DefaultController;
use yii\debug\{DebugAsset, LogTarget, Module, Panel};
use yii\debug\panels\LogPanel;
use yii\debug\tests\provider\ModuleProvider;
use yii\debug\tests\support\TestCase;
use yii\log\{Dispatcher, Target as LogTargetBase};
use yii\web\{AssetManager, ErrorHandlerRenderEvent, ForbiddenHttpException, Response, View};

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
            self::fail("Could not create asset base path: {$assetBasePath}");
        }

        $app->set(
            'assetManager',
            [
                'class' => AssetManager::class,
                'basePath' => $assetBasePath,
                'baseUrl' => '/assets',
            ],
        );

        $controller = new DefaultController('default', $module);

        $controller->layout = false;

        $output = $controller->actionPhpInfo();

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

        $action = new Action('index', new Controller('default', $module));

        self::assertTrue(
            $module->beforeAction($action),
            "'beforeAction' must succeed when access is allowed.",
        );
        self::assertFalse(
            $fakeTarget->enabled,
            "'beforeAction' must disable log targets when 'enableDebugLogs' is false.",
        );
    }

    public function testBeforeActionReturnsFalseForToolbarRoutesUnderAccessDenial(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['10.0.0.1'];
        $module->disableIpRestrictionWarning = true;

        Yii::$app->setModule('debug', $module);

        $action = new Action('toolbar', new Controller('default', $module));

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        self::assertFalse(
            $module->beforeAction($action),
            "Denied access to the 'toolbar' action must return 'false' instead of throwing.",
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

    public function testBootstrapClosuresWireToolbarAndDebugHeaderListeners(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        // Trigger the EVENT_BEFORE_REQUEST closure → registers `setDebugHeaders` on the response.
        Yii::$app->trigger(Application::EVENT_BEFORE_REQUEST);

        self::assertTrue(
            Yii::$app->getResponse()->hasEventHandlers(Response::EVENT_AFTER_PREPARE),
            "EVENT_BEFORE_REQUEST closure must attach 'setDebugHeaders' to the response.",
        );

        // Trigger the EVENT_BEFORE_ACTION closure → registers `renderToolbar` on the view.
        $event = new ActionEvent(new Action('view', new Controller('default', $module)));

        Yii::$app->trigger(Application::EVENT_BEFORE_ACTION, $event);

        self::assertTrue(
            Yii::$app->getView()->hasEventHandlers(View::EVENT_END_BODY),
            "EVENT_BEFORE_ACTION closure must attach 'renderToolbar' to the view.",
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

    public function testDebugAssetShipsLocalFrameworkAgnosticScript(): void
    {
        $asset = new DebugAsset();

        self::assertSame(
            [
                'dist/js/debug.min.js',
                'dist/js/theme-toggle.min.js',
                'dist/js/history-cursor.min.js',
            ],
            $asset->js,
            'DebugAsset must ship the local core scripts: debug + theme-toggle + history-cursor.',
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
            '0.1.0',
            $module->getVersion(),
            'Module version must read from the registered extension entry.',
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
            "data-url=\"/index.php?r=debug%2Fdefault%2Ftoolbar-data&amp;tag={$logTarget->tag}\"",
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

    public function testHtmlTitleResolvesCallableTitle(): void
    {
        $module = new Module('debug');

        $module->pageTitle = static fn(string $base): string => "Title for {$base}";

        self::assertStringStartsWith(
            'Title for ',
            $module->htmlTitle(),
            "Callable 'pageTitle' must be invoked with the base URL.",
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

    public function testInitPanelsOverridesCorePanelByMatchingId(): void
    {
        $override = new LogPanel();
        $module = new Module('debug', null, ['panels' => ['log' => $override]]);

        self::assertArrayHasKey('log', $module->panels, 'Override entry must surface under the same id.');
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

        $response = Yii::$app->getResponse();

        $module->setDebugHeaders(new Event(['sender' => $response]));

        $headers = $response->getHeaders();

        self::assertTrue(
            $headers->has('X-Debug-Tag'),
            "'X-Debug-Tag' header must be set.",
        );
        self::assertTrue(
            $headers->has('X-Debug-Duration'),
            "'X-Debug-Duration' header must be set.",
        );
        self::assertTrue(
            $headers->has('X-Debug-Link'),
            "'X-Debug-Link' header must be set.",
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
        $module->logTarget = \yii\debug\tests\support\stub\NotALogTarget::class;

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

        $controller = new DefaultController('default', $module);

        $data = $controller->actionToolbarData($tag);

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
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
        Yii::getLogger()->dispatcher = self::createStub(\yii\log\Dispatcher::class);
    }
}
