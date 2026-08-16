<?php

declare(strict_types=1);

namespace yii\debug\tests\controllers;

use Exception;
use LogicException;
use PHPForge\Debug\Storage\{PanelSnapshot, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{ActionEvent, InvalidConfigException};
use yii\db\Connection;
use yii\debug\controllers\DefaultController;
use yii\debug\{LogTarget, Module};
use yii\debug\panels\config\ConfigSnapshot;
use yii\debug\panels\{ConfigPanel, MailPanel};
use yii\debug\panels\db\DbSnapshot;
use yii\debug\panels\log\LogSnapshot;
use yii\debug\panels\request\RequestSnapshot;
use yii\debug\tests\support\stub\{MinimalToolbarPanel, StubSnapshot};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\SidebarView;
use yii\web\{AssetManager, NotFoundHttpException, Response};

use function file_put_contents;
use function mkdir;

/**
 * Unit tests for {@see DefaultController} covering every public action.
 */
#[Group('controllers')]
#[Group('default')]
final class DefaultControllerTest extends TestCase
{
    public function testActionDownloadMailStreamsExistingMailFile(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $mailPanel = $module->panels['mail'] ?? null;

        self::assertInstanceOf(
            MailPanel::class,
            $mailPanel,
            'Mail panel must be wired.',
        );

        $mailDir = Yii::getAlias($mailPanel->mailPath);

        @mkdir($mailDir, 0o777, true);

        $file = 'sample.eml';

        file_put_contents("{$mailDir}/{$file}", 'From: a@b');

        $response = $controller->actionDownloadMail($file);

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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionIndex();

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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionIndex();

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

    public function testActionPhpInfoRendersPhpInfoView(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionPhpInfo();

        self::assertNotSame(
            '',
            $html,
            "'phpinfo' view must render markup.",
        );
    }

    public function testActionsAdoptsEveryRegisteredPanelAction(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $actions = $controller->actions();

        self::assertNotSame(
            [],
            $actions,
            'Controller actions map must aggregate at least one panel action.',
        );
        self::assertArrayHasKey(
            'db-explain',
            $actions,
            "'db-explain' action must be adopted from the DbPanel.",
        );
        self::assertArrayHasKey(
            'queue-job',
            $actions,
            "'queue-job' action must be adopted from the QueuePanel.",
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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $result = $controller->actionToolbarData('tag-toolbar-stub');

        $items = $result['items'] ?? [];

        self::assertNotSame(
            [],
            $items,
            "Payload must expose 'items' for the wired panels.",
        );

        $stubItem = null;

        foreach ($items as $item) {
            if (($item['id'] ?? null) === 'stub') {
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
            'yii\debug\controllers',
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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $result = $controller->actionToolbarData('does-not-exist');

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
            array_column(MockerState::getTraces('yii\debug\controllers', 'sleep'), 'arguments'),
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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $result = $controller->actionToolbarData('tag-toolbar-ok');

        self::assertSame(
            'tag-toolbar-ok',
            $result['tag'],
            'Payload must echo the active tag.',
        );
        self::assertNotNull(
            $result['configUrl'] ?? null,
            "'configUrl' must surface when the Config panel is wired.",
        );

        $iconBaseUrl = $result['iconBaseUrl'];

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
            $result['logo'],
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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $result = $controller->actionToolbarData('tag-asset-fail');

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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionView();

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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionView('tag-view-panel', 'request');

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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionView('tag-view-error', 'request');

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

    public function testBeforeActionForcesHtmlResponseFormat(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $action = $controller->createAction('index');

        self::assertNotNull(
            $action,
            "'index' must resolve to an action object.",
        );

        self::assertTrue(
            $controller->beforeAction($action), // @phpstan-ignore argument.type
            'Accepted action must preserve the parent guard result.',
        );

        self::assertSame(
            Response::FORMAT_HTML,
            Yii::$app->response->format,
            "'beforeAction' must force the HTML response format.",
        );

        $shell = Yii::$app->view->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            "'beforeAction' must install a typed bare shell context.",
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

    public function testBeforeActionReturnsFalseWhenParentGuardRejectsAction(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $action = $controller->createAction('index');

        self::assertNotNull(
            $action,
            "'index' must resolve to an action object.",
        );

        $controller->on(
            DefaultController::EVENT_BEFORE_ACTION,
            static function (ActionEvent $event): void {
                $event->isValid = false;
            },
        );

        self::assertFalse(
            $controller->beforeAction($action), // @phpstan-ignore argument.type
            'The debug controller must preserve a parent action rejection.',
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

        $controller = new DefaultController('default', $module);

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
            $controller,
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
            $controller,
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

        $controller = new DefaultController('default', $module);

        $first = $controller->getManifest();

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
        $cached = $controller->getManifest();

        self::assertArrayNotHasKey(
            'tag-second',
            $cached,
            'Cached manifest must not see new tags.',
        );

        $reloaded = $controller->getManifest(true);

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
            'yii\debug\controllers',
            'sleep',
            [],
            0,
            true,
        );

        $controller = new DefaultController('default', $module);

        try {
            $controller->loadData('tag-missing');
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
            MockerState::getTraces('yii\debug\controllers', 'sleep'),
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

        $controller = new DefaultController('default', $module);

        $controller->loadData('tag-load');

        self::assertSame(
            'tag-load',
            $controller->summary?->tag,
            'Loaded summary must echo the active tag.',
        );
    }

    public function testLoadDataReloadsManifestAfterWaitingForLateTag(): void
    {
        $module = $this->bootDebugModule();
        $controller = new DefaultController('default', $module);

        MockerState::addCondition(
            'yii\debug\controllers',
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

        $controller->loadData('tag-late', 1);

        self::assertSame(
            'tag-late',
            $controller->summary?->tag,
            'Retry must reload the manifest and load a tag that appeared during the wait.',
        );
    }

    public function testMainLayoutRequiresShellContext(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        unset(Yii::$app->view->params['debugShell']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The debug layout requires a ShellContext.');

        Yii::$app->view->renderFile(
            dirname(__DIR__, 2) . '/src/views/layouts/main.php',
            ['content' => ''],
            $controller,
        );
    }

    public function testPrimeThemeContextResolvesDarkFromGlobalCookieFallback(): void
    {
        $module = $this->bootDebugModule();

        // Query is absent and the request component's cookie collection is empty: the $_COOKIE fallback wins.
        $_COOKIE['yii-debug-toolbar-theme'] = 'dark';

        try {
            $controller = new DefaultController('default', $module);
            $theme = $this->invoke($controller, 'resolveTheme');
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

        $controller = new DefaultController('default', $module);

        $theme = $this->invoke($controller, 'resolveTheme');

        self::assertSame(
            'dark',
            $theme,
            "The 'yii_debug_theme' query value must select the dark theme case-insensitively.",
        );
    }

    public function testPrimeThemeContextResolvesLightThemeByDefault(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $theme = $this->invoke($controller, 'resolveTheme');

        self::assertSame(
            'light',
            $theme,
            "Missing theme inputs must default to 'light'.",
        );
    }

    public function testSharedShellRendersPeakMemoryAndDisabledConfigChip(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

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

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'No debug data have been collected yet',
        );

        $controller->actionIndex();
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetIsMissing(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        // Skip 'bootstrap()' so 'logTarget' stays as the default config array.
        $controller = new DefaultController('default', $module);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'debug module logTarget must be initialized',
        );

        $this->invoke(
            $controller,
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

        $controller = new DefaultController('default', $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'does not contain summary data',
        );

        $controller->loadData($tag);
    }

    public function testThrowNotFoundHttpExceptionWhenMailFileDoesNotExist(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'Mail file not found',
        );

        $controller->actionDownloadMail('missing-file.eml');
    }

    public function testThrowNotFoundHttpExceptionWhenMailFileNameContainsSlash(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'Mail file not found',
        );

        $controller->actionDownloadMail('subdir/sample.eml');
    }

    public function testThrowNotFoundHttpExceptionWhenMailPanelIsMissing(): void
    {
        $module = $this->bootDebugModule();

        // Drop the mail panel so 'getMailPanel()' must throw.
        unset($module->panels['mail']);

        $controller = new DefaultController('default', $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'Mail panel not found.',
        );

        $controller->actionDownloadMail('sample.eml');
    }

    public function testThrowNotFoundHttpExceptionWhenManifestIsEmptyForView(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            'No debug data have been collected yet',
        );

        $controller->actionView(null);
    }

    public function testThrowNotFoundHttpExceptionWhenPanelIsNotRegistered(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            "Debug panel 'missing' not found.",
        );

        $this->invoke(
            $controller,
            'getPanel',
            ['missing'],
        );
    }

    public function testThrowNotFoundHttpExceptionWhenTagIsNotFoundAfterRetries(): void
    {
        $module = $this->bootDebugModule();
        // Persist a different tag so the retry path runs but 'tag-rotated' never appears.
        $this->writeSnapshot($module, 'tag-other', []);

        $controller = new DefaultController('default', $module);

        MockerState::addCondition(
            'yii\debug\controllers',
            'sleep',
            [],
            0,
            true,
        );

        try {
            $controller->loadData('tag-rotated', 1);
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
            array_column(MockerState::getTraces('yii\debug\controllers', 'sleep'), 'arguments'),
            'One retry must wait exactly once for one second between the two attempts.',
        );
    }

    private function bootDebugModule(): Module
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

        $module = new Module('debug');

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
