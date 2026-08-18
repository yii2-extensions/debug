<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use Exception;
use LogicException;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\{PanelSnapshot, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\debug\actions\{
    Action as DebugAction,
    DownloadMailAction,
    IndexAction,
    PhpInfoAction,
    ToolbarDataAction,
    ViewAction,
};
use yii\debug\collectors\MailCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\panels\ConfigPanel;
use yii\debug\tests\support\stub\{ConfigurableAction, MinimalToolbarPanel, StubSnapshot};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\SidebarView;
use yii\web\{AssetManager, NotFoundHttpException, Response};

use function file_put_contents;
use function is_array;
use function mkdir;

/**
 * Unit tests for the standalone debugger actions covering every dispatched endpoint and the shared plumbing.
 */
#[Group('actions')]
final class DebugActionsTest extends TestCase
{
    public function testActionDownloadMailStreamsExistingMailFile(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);

        $mailCollector = $module->getCollectorCoordinator()->collector('mail');

        self::assertInstanceOf(
            MailCollector::class,
            $mailCollector,
            'Mail collector must be wired.',
        );

        $mailDir = Yii::getAlias($mailCollector->mailPath);

        @mkdir($mailDir, 0o777, true);

        $file = 'sample.eml';

        file_put_contents("{$mailDir}/{$file}", 'From: a@b');

        $response = $this->runDebugAction(new DownloadMailAction('download-mail'), $module, ['file' => $file]);

        self::assertInstanceOf(
            Response::class,
            $response,
            "Download must return a 'Response'.",
        );
    }

    public function testActionIndexPropagatesCursorFromQueryParam(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-index-cursor',
            [],
        );

        $_GET['cursor'] = 'tag-index-cursor';

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = $this->runDebugAction(new IndexAction('index'), $module);

        self::assertNotSame(
            '',
            $html,
            'Index must still render when a cursor query param is present.',
        );
    }

    public function testActionIndexRendersWhenManifestIsPopulated(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-index',
            [
                'config' => ConfigSnapshot::capture(
                    [
                        'application' => ['yii' => 'captured-index-yii'],
                        'php' => ['version' => 'captured-index-php'],
                    ],
                ),
            ],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = $this->runDebugAction(new IndexAction('index'), $module);

        self::assertNotSame(
            '',
            $html,
            'Rendered index must not be empty.',
        );

        $shell = Yii::$app->view->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            'Index must install the typed shell context.',
        );
        self::assertSame(
            'captured-index-php',
            $shell->phpVersion,
            'Index must load the latest entry before building its shell.',
        );
    }

    public function testActionMapAdoptsEveryRegisteredPanelAction(): void
    {
        $module = $this->bootDebugModule();

        self::assertArrayHasKey(
            'index',
            $module->actionMap,
            "'index' must be registered as a built-in standalone action.",
        );
        self::assertArrayHasKey(
            'db-explain',
            $module->actionMap,
            "'db-explain' action must be adopted from the DbPanel.",
        );
        self::assertArrayHasKey(
            'queue-job',
            $module->actionMap,
            "'queue-job' action must be adopted from the QueuePanel.",
        );
    }

    public function testActionPhpInfoRendersPhpInfoView(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = $this->runDebugAction(new PhpInfoAction('php-info'), $module);

        self::assertNotSame(
            '',
            $html,
            "'phpinfo' view must render markup.",
        );
    }

    public function testActionToolbarDataInjectsDefaultsForIncompletePanelEnvelopes(): void
    {
        $module = $this->bootDebugModule();

        // Wire a panel whose 'getToolbarData()' omits 'id', 'title', and 'url' so the controller's defaults kick in.
        $stub = new MinimalToolbarPanel();

        $stub->id = 'stub';
        $stub->module = $module;
        $module->panels['stub'] = $stub;

        $this->writeSnapshot(
            $module,
            'tag-toolbar-stub',
            ['stub' => StubSnapshot::capture([])],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $result = $this->runDebugAction(new ToolbarDataAction('toolbar-data'), $module, ['tag' => 'tag-toolbar-stub']);

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );

        $items = $result['items'] ?? [];

        self::assertIsArray(
            $items,
            "'items' must be a list.",
        );
        self::assertNotSame(
            [],
            $items,
            "Payload must expose 'items' for the wired panels.",
        );

        $stubItem = null;

        foreach ($items as $item) {
            if (is_array($item) && ($item['id'] ?? null) === 'stub') {
                $stubItem = $item;
                break;
            }
        }

        self::assertNotNull(
            $stubItem,
            "Stub panel chip must surface in 'items'.",
        );
        self::assertArrayHasKey(
            'title',
            $stubItem,
            "'title' must be injected when missing.",
        );
        self::assertArrayHasKey(
            'url',
            $stubItem,
            "'url' must be injected when missing.",
        );
    }

    public function testActionToolbarDataReturnsJsonErrorWhenTagIsUnknown(): void
    {
        $module = $this->bootDebugModule();

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [],
            0,
            true,
        );

        $this->writeSnapshot(
            $module,
            'tag-toolbar',
            [],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $result = $this->runDebugAction(new ToolbarDataAction('toolbar-data'), $module, ['tag' => 'does-not-exist']);

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );
        self::assertSame(
            'Debug tag not found.',
            $result['error'] ?? null,
            'Rotated tag must surface as a JSON error envelope.',
        );
        self::assertSame(
            404,
            Yii::$app->response->getStatusCode(),
            "Response must emit a '404' status code.",
        );
        self::assertSame(
            array_fill(0, 5, [1]),
            array_column(MockerState::getTraces('yii\debug\actions', 'sleep'), 'arguments'),
            'Toolbar metadata must retry five times with a one-second wait between attempts.',
        );
    }

    public function testActionToolbarDataReturnsMetadataPayloadForKnownTag(): void
    {
        $module = $this->bootDebugModule();

        // Persist data for every panel that surfaces toolbar chips so the controller's panel iteration runs end-to-end.
        $this->writeSnapshot(
            $module,
            'tag-toolbar-ok',
            [
                'config' => ConfigSnapshot::capture([]),
                'db' => new DbSnapshot([]),
                'log' => LogSnapshot::capture([]),
                'request' => RequestSnapshot::capture(['statusCode' => 200]),
            ],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $result = $this->runDebugAction(new ToolbarDataAction('toolbar-data'), $module, ['tag' => 'tag-toolbar-ok']);

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );
        self::assertSame(
            'tag-toolbar-ok',
            $result['tag'] ?? null,
            'Payload must echo the active tag.',
        );
        self::assertNotNull(
            $result['configUrl'] ?? null,
            "'configUrl' must surface when the Config panel is wired.",
        );

        $iconBaseUrl = $result['iconBaseUrl'] ?? null;

        self::assertIsString(
            $iconBaseUrl,
            "'iconBaseUrl' must be a string.",
        );
        self::assertStringStartsWith(
            '/assets/',
            $iconBaseUrl,
            "'iconBaseUrl' must use the published asset URL, not its filesystem path.",
        );
        self::assertStringEndsWith(
            '/svg/',
            $iconBaseUrl,
            "'iconBaseUrl' must point to the published SVG directory.",
        );
        self::assertSame(
            "{$iconBaseUrl}yii.svg",
            $result['logo'] ?? null,
            'Published Yii logo must use the SVG asset URL.',
        );
    }

    public function testActionToolbarDataSwallowsAssetManagerFailure(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-asset-fail',
            ['config' => ConfigSnapshot::capture([])],
        );

        // Replace the asset manager with one whose 'basePath' points at a non-writable path so 'publish()' throws.
        Yii::$app->set(
            'assetManager',
            new AssetManager(['basePath' => '/dev/null/does-not-exist', 'baseUrl' => '/x']),
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $result = $this->runDebugAction(new ToolbarDataAction('toolbar-data'), $module, ['tag' => 'tag-asset-fail']);

        self::assertIsArray(
            $result,
            'Payload must decode to an array.',
        );
        self::assertSame(
            '',
            $result['iconBaseUrl'] ?? null,
            "Failed publish must leave 'iconBaseUrl' empty.",
        );
    }

    public function testActionViewFallsBackToFirstTagWhenTagIsNull(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-view-first',
            ['log' => LogSnapshot::capture([])],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = $this->runDebugAction(new ViewAction('view'), $module);

        self::assertNotSame(
            '',
            $html,
            "'view' must render the most recent tag when none is given.",
        );
    }

    public function testActionViewRendersExplicitPanel(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-view-panel',
            ['request' => RequestSnapshot::capture(['statusCode' => 200])],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = $this->runDebugAction(new ViewAction('view'), $module, ['tag' => 'tag-view-panel', 'panel' => 'request']);

        self::assertNotSame(
            '',
            $html,
            "Explicit panel id must render the panel's view.",
        );
    }

    public function testActionViewRendersPanelExceptionWhenPanelReportsError(): void
    {
        $module = $this->bootDebugModule();

        // Persist an exception on the 'request' panel via the failure channel of the snapshot.
        $error = new RuntimeException('Boom');

        $this->writeSnapshotWithExceptions(
            $module,
            'tag-view-error',
            ['request' => $error],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = $this->runDebugAction(new ViewAction('view'), $module, ['tag' => 'tag-view-error', 'panel' => 'request']);

        self::assertSame(
            500,
            Yii::$app->response->getStatusCode(),
            'Panel error must surface a 500 status code.',
        );
        self::assertNotSame(
            '',
            $html,
            'Exception view must render markup.',
        );
    }

    public function testAppDispatchesBuiltInDebugRouteThroughActionMap(): void
    {
        $module = $this->bootDebugModule();

        // Application-level dispatch of a module-prefixed route reaches the module through 'createStandaloneAction()',
        // which the module overrides to resolve the endpoint from its action map (the URL manager path).
        $html = Yii::$app->runAction("{$module->getUniqueId()}/php-info");

        self::assertIsString(
            $html,
            'Module-prefixed dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            'phpinfo',
            $html,
            "Route 'debug/php-info' must resolve from the action map.",
        );
    }

    public function testAppDispatchesPanelDebugRouteThroughActionMap(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-panel-route',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        // The 'db-explain' id maps to the sub-namespaced 'actions\db\ExplainAction', which convention discovery
        // cannot derive; the action-map lookup keeps the URL reachable.
        try {
            $html = Yii::$app->runAction(
                "{$module->getUniqueId()}/db-explain",
                ['seq' => '0', 'tag' => 'tag-panel-route'],
            );
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertIsString(
            $html,
            'Module-prefixed panel dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>',
            $html,
            "Sub-namespaced 'db-explain' action must resolve from the action map.",
        );
    }

    public function testAppDispatchPreservesConfiguredActionProperties(): void
    {
        $module = $this->bootDebugModule();

        // A custom array-shaped entry must keep its configured properties, matching direct 'runMappedAction' dispatch.
        $module->actionMap['configurable'] = ['class' => ConfigurableAction::class, 'label' => 'configured'];

        $result = Yii::$app->runAction("{$module->getUniqueId()}/configurable");

        self::assertSame(
            'configured',
            $result,
            'Array-shaped action config must apply configured properties.',
        );
    }

    public function testBeforeRunForcesHtmlResponseFormatAndBareShell(): void
    {
        $module = $this->bootDebugModule();

        $this->runDebugAction(new PhpInfoAction('php-info'), $module);

        self::assertSame(
            Response::FORMAT_HTML,
            Yii::$app->response->format,
            "'beforeRun' must force the HTML response format.",
        );

        $shell = Yii::$app->view->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            "'beforeRun' must install a typed bare shell context.",
        );
        self::assertFalse(
            $shell->useShell,
            'Bare action context must not render the full debug shell.',
        );
        self::assertArrayHasKey(
            'lang',
            $shell->debugThemeAttributes,
            'Bare shell document attributes must declare a language.',
        );
    }

    public function testCreateShellContextSupportsEmptyManifestAndNumericPeakMemory(): void
    {
        $module = $this->bootDebugModule();

        $configPanel = $module->panels['config'] ?? null;

        self::assertInstanceOf(
            ConfigPanel::class,
            $configPanel,
            'Config panel must be wired.',
        );

        $this->hydratePanel(
            $configPanel,
            ConfigSnapshot::capture(
                [
                    'application' => ['yii' => 'captured-yii-version'],
                    'php' => ['version' => 'captured-php-version'],
                ],
            ),
        );

        $action = $this->wireAction(new ViewAction('view'), $module);

        $summary = new RequestSummary(
            tag: 'tag-shell',
            url: 'dummy',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: 1_048_576,
        );

        $context = $this->invoke(
            $action,
            'createShellContext',
            [
                ShellContext::MODE_INDEX,
                [],
                null,
                $summary,
                new SidebarView(null, []),
            ],
        );

        self::assertInstanceOf(
            ShellContext::class,
            $context,
            'The factory must return a typed shell context.',
        );
        self::assertNull(
            $context->configUrl,
            'An empty manifest must disable the configuration link.',
        );
        self::assertNotNull(
            $context->peakMemory,
            'Numeric peak memory must be formatted for the shell header.',
        );
        self::assertSame(
            'captured-yii-version',
            $context->yiiVersion,
            'Shell must prefer the Yii version stored with the debug entry.',
        );
        self::assertSame(
            'captured-php-version',
            $context->phpVersion,
            'Shell must prefer the PHP version stored with the debug entry.',
        );
        self::assertTrue(
            $context->useShell,
            'Index context must render the full debug shell.',
        );
        self::assertArrayHasKey(
            'lang',
            $context->debugThemeAttributes,
            'Full shell document attributes must declare a language.',
        );

        $activeContext = $this->invoke(
            $action,
            'createShellContext',
            [
                ShellContext::MODE_VIEW,
                ['manifest-tag' => $summary],
                'active-tag',
                null,
                new SidebarView(null, []),
            ],
        );

        self::assertInstanceOf(
            ShellContext::class,
            $activeContext,
            'The factory must return a typed active shell context.',
        );
        self::assertStringContainsString(
            'tag=active-tag',
            $activeContext->configUrl ?? '',
            'Configuration link must target the explicitly active entry.',
        );
    }

    public function testGetManifestCachesResultAndReloadsOnForce(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-first',
            [],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);

        $first = $action->getManifest();

        self::assertArrayHasKey(
            'tag-first',
            $first,
            "Initial manifest must include 'tag-first'.",
        );

        $this->writeSnapshot(
            $module,
            'tag-second',
            [],
        );

        // Without forceReload the cached manifest must persist.
        $cached = $action->getManifest();

        self::assertArrayNotHasKey(
            'tag-second',
            $cached,
            'Cached manifest must not see new tags.',
        );

        $reloaded = $action->getManifest(true);

        self::assertArrayHasKey(
            'tag-second',
            $reloaded,
            'Forced reload must surface freshly written tags.',
        );
    }

    public function testLoadDataDoesNotRetryByDefault(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-other', []);

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [],
            0,
            true,
        );

        $action = $this->wireAction(new ViewAction('view'), $module);

        try {
            $action->loadData('tag-missing');
            self::fail('Missing tag must throw after the initial attempt.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame(
                "Unable to find debug data tagged with 'tag-missing'.",
                $exception->getMessage(),
                'Missing tag must report the requested identifier.',
            );
        }

        self::assertSame(
            [],
            MockerState::getTraces('yii\debug\actions', 'sleep'),
            'Default load must not wait or retry.',
        );
    }

    public function testLoadDataPopulatesSummaryWhenTagIsKnown(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-load',
            [],
        );

        $action = $this->wireAction(new ViewAction('view'), $module);

        $action->loadData('tag-load');

        self::assertSame(
            'tag-load',
            $action->summary?->tag,
            'Loaded summary must echo the active tag.',
        );
    }

    public function testLoadDataReloadsManifestAfterWaitingForLateTag(): void
    {
        $module = $this->bootDebugModule();
        $action = $this->wireAction(new ViewAction('view'), $module);

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [1],
            function (int $seconds) use ($module): int {
                self::assertSame(
                    1,
                    $seconds,
                    'Retry wait must receive the documented interval.',
                );

                $this->writeSnapshot($module, 'tag-late', []);

                return 0;
            },
        );

        $action->loadData('tag-late', 1);

        self::assertSame(
            'tag-late',
            $action->summary?->tag,
            'Retry must reload the manifest and load a tag that appeared during the wait.',
        );
    }

    public function testMainLayoutRequiresShellContext(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);

        unset(Yii::$app->view->params['debugShell']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The debug layout requires a ShellContext.');

        Yii::$app->view->renderFile(
            dirname(__DIR__, 2) . '/src/views/layouts/main.php',
            ['content' => ''],
            $action,
        );
    }

    public function testPrimeThemeContextResolvesDarkFromGlobalCookieFallback(): void
    {
        $module = $this->bootDebugModule();

        // Query is absent and the request component's cookie collection is empty: the $_COOKIE fallback wins.
        $_COOKIE['yii-debug-toolbar-theme'] = 'dark';

        try {
            $action = $this->wireAction(new ViewAction('view'), $module);
            $theme = $this->invoke($action, 'resolveTheme');
        } finally {
            unset($_COOKIE['yii-debug-toolbar-theme']);
        }

        self::assertSame(
            'dark',
            $theme,
            '$_COOKIE fallback must seed the dark theme.',
        );
    }

    public function testPrimeThemeContextResolvesDarkFromQueryParam(): void
    {
        $module = $this->bootDebugModule();

        $_GET['yii_debug_theme'] = 'DARK';

        $action = $this->wireAction(new ViewAction('view'), $module);

        $theme = $this->invoke($action, 'resolveTheme');

        self::assertSame(
            'dark',
            $theme,
            "The 'yii_debug_theme' query value must select the dark theme case-insensitively.",
        );
    }

    public function testPrimeThemeContextResolvesLightThemeByDefault(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);

        $theme = $this->invoke($action, 'resolveTheme');

        self::assertSame(
            'light',
            $theme,
            "Missing theme inputs must default to 'light'.",
        );
    }

    public function testRunActionDispatchesDbExplainThroughActionMapWithInjectedPanel(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot(
            $module,
            'tag-di',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $module->runAction('db-explain', ['seq' => '0', 'tag' => 'tag-di']);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertIsString(
            $html,
            'Action-map dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>',
            $html,
            'Injected panel must serve the captured query through dispatch.',
        );
    }

    public function testRunActionDispatchesPhpInfoThroughActionMap(): void
    {
        $module = $this->bootDebugModule();

        $html = $module->runAction('php-info');

        self::assertIsString(
            $html,
            'Action-map dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            'phpinfo',
            $html,
            "Route 'php-info' must resolve to the phpinfo action.",
        );
    }

    public function testSharedShellRendersPeakMemoryAndDisabledConfigChip(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);


        $html = Yii::$app->view->renderFile(
            Module::VIEW_PATH_ALIAS . '/_shell.php',
            [
                'actionIcon' => '<svg/>',
                'actionLabel' => 'Config',
                'actionTitle' => 'No requests captured yet',
                'actionUrl' => null,
                'content' => '<p>Content</p>',
                'debugTheme' => 'light',
                'historyUrl' => '/debug',
                'mode' => 'index',
                'peakMemory' => '1.21 MB',
                'phpIcon' => '<svg/>',
                'phpVersion' => '8.5.0',
                'sidebar' => '<aside>Sidebar</aside>',
                'themeIconMoon' => '<svg/>',
                'themeIconSun' => '<svg/>',
                'useShell' => true,
                'yiiIcon' => '<svg/>',
                'yiiVersion' => '2.0.0',
            ],
        );

        self::assertStringContainsString(
            '1.21 MB',
            $html,
            'Peak memory chip must surface when value is non-null.',
        );
        self::assertStringContainsString(
            'is-disabled',
            $html,
            "Null 'actionUrl' must render the disabled config chip.",
        );
    }

    public function testThrowExceptionWhenIndexCalledOnEmptyManifest(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);


        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'No debug data have been collected yet',
        );

        $this->runDebugAction(new IndexAction('index'), $module);
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetIsMissing(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        // Skip 'bootstrap()' so 'logTarget' stays as the default config array.
        $action = $this->wireAction(new ViewAction('view'), $module);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'debug module logTarget must be initialized',
        );

        $this->invoke(
            $action,
            'getLogTarget',
        );
    }

    public function testThrowNotFoundHttpExceptionWhenLoadedTagLacksSummary(): void
    {
        $module = $this->bootDebugModule();

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            "'logTarget' must be wired by bootstrap.",
        );

        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $tag = 'tag-no-summary';

        $this->writeDebugSnapshot(
            $module,
            $tag,
            [],
        );

        file_put_contents("{$dataPath}/{$tag}.json", '{"version":3,"panels":{},"failures":{}}');

        $action = $this->wireAction(new ViewAction('view'), $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'does not contain summary data',
        );

        $action->loadData($tag);
    }

    public function testThrowNotFoundHttpExceptionWhenMailCollectorIsMissing(): void
    {
        $module = $this->bootDebugModule(collectorless: true);

        $action = $this->wireAction(new ViewAction('view'), $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'Mail collector not found.',
        );

        $this->runDebugAction(new DownloadMailAction('download-mail'), $module, ['file' => 'sample.eml']);
    }

    public function testThrowNotFoundHttpExceptionWhenMailFileDoesNotExist(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'Mail file not found',
        );

        $this->runDebugAction(new DownloadMailAction('download-mail'), $module, ['file' => 'missing-file.eml']);
    }

    public function testThrowNotFoundHttpExceptionWhenMailFileNameContainsSlash(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'Mail file not found',
        );

        $this->runDebugAction(new DownloadMailAction('download-mail'), $module, ['file' => 'subdir/sample.eml']);
    }

    public function testThrowNotFoundHttpExceptionWhenManifestIsEmptyForView(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);


        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'No debug data have been collected yet',
        );

        $this->runDebugAction(new ViewAction('view'), $module);
    }

    public function testThrowNotFoundHttpExceptionWhenPanelIsNotRegistered(): void
    {
        $module = $this->bootDebugModule();

        $action = $this->wireAction(new ViewAction('view'), $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            "Debug panel 'missing' not found.",
        );

        $this->invoke(
            $action,
            'getPanel',
            ['missing'],
        );
    }

    public function testThrowNotFoundHttpExceptionWhenTagIsNotFoundAfterRetries(): void
    {
        $module = $this->bootDebugModule();
        // Persist a different tag so the retry path runs but 'tag-rotated' never appears.
        $this->writeSnapshot($module, 'tag-other', []);

        $action = $this->wireAction(new ViewAction('view'), $module);

        MockerState::addCondition(
            'yii\debug\actions',
            'sleep',
            [],
            0,
            true,
        );

        try {
            $action->loadData('tag-rotated', 1);
            self::fail('Missing tag must throw after the configured retry.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame(
                "Unable to find debug data tagged with 'tag-rotated'.",
                $exception->getMessage(),
                'Missing tag must report the requested identifier.',
            );
        }

        self::assertSame(
            [[1]],
            array_column(MockerState::getTraces('yii\debug\actions', 'sleep'), 'arguments'),
            'One retry must wait exactly once for one second between the two attempts.',
        );
    }

    private function bootDebugModule(bool $collectorless = false): Module
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'class' => AssetManager::class,
                        'basePath' => dirname(__DIR__, 2) . '/runtime/assets',
                        'baseUrl' => '/assets',
                    ],
                    'db' => ['class' => Connection::class, 'dsn' => 'sqlite::memory:'],
                ],
            ],
        );

        @mkdir(Yii::getAlias('@runtime/assets'), 0o777, true);

        $module = $collectorless
            ? new class ('debug') extends Module {
                protected function coreCollectors(): array
                {
                    return [];
                }
            }
        : new Module('debug');

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        // Purge any residue from prior tests so each run starts with an empty manifest.
        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $files = glob("{$dataPath}/*.json");

        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        return $module;
    }

    private static function queryRow(string $query): QueryRow
    {
        return new QueryRow(
            type: 'SELECT',
            query: $query,
            duration: 50.0,
            trace: [],
            traceHash: 'hash',
            timestamp: 1_700_000_000_000.0,
            seq: 0,
            duplicate: 1,
            rows: null,
        );
    }

    /**
     * Dispatches a wired standalone action through its full run lifecycle.
     *
     * @param array<string, mixed> $params Route parameters bound to the action.
     */
    private function runDebugAction(DebugAction $action, Module $module, array $params = []): mixed
    {
        $action->setModule($module);

        Yii::$app->requestedAction = $action;
        Yii::$app->requestedRoute = $action->getUniqueId();

        return $action->runWithParams($params);
    }

    /**
     * Wires a standalone debugger action to the module without running it.
     */
    private function wireAction(DebugAction $action, Module $module): DebugAction
    {
        $action->setModule($module);

        return $action;
    }

    /**
     * @param array<string, PanelSnapshot> $panels
     */
    private function writeSnapshot(Module $module, string $tag, array $panels): void
    {
        $this->writeDebugSnapshot($module, $tag, $panels);
    }

    /**
     * @param array<string, \Throwable> $exceptions Per-panel exception map keyed by panel id.
     */
    private function writeSnapshotWithExceptions(Module $module, string $tag, array $exceptions): void
    {
        $this->writeDebugSnapshot($module, $tag, [], failures: $exceptions);
    }
}
