<?php

declare(strict_types=1);

namespace yii\debug\tests\controllers;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use UnexpectedValueException;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\debug\controllers\DefaultController;
use yii\debug\{FlattenException, LogTarget, Module};
use yii\debug\panels\MailPanel;
use yii\debug\tests\support\stub\MinimalToolbarPanel;
use yii\debug\tests\support\TestCase;
use yii\web\{AssetManager, NotFoundHttpException, Response};

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

        $this->writeSnapshot($module, 'tag-index-cursor', []);

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

        $this->writeSnapshot($module, 'tag-index', []);

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionIndex();

        self::assertNotSame(
            '',
            $html,
            'Rendered index must not be empty.',
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
    }

    public function testActionToolbarDataInjectsDefaultsForIncompletePanelEnvelopes(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-toolbar-stub', ['stub' => []]);

        // Wire a panel whose 'getToolbarData()' omits 'id', 'title', and 'url' so the controller's defaults kick in.
        $stub = new MinimalToolbarPanel();

        $stub->id = 'stub';
        $stub->module = $module;
        $module->panels['stub'] = $stub;

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

        $this->writeSnapshot($module, 'tag-toolbar', []);

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
    }

    public function testActionToolbarDataReturnsMetadataPayloadForKnownTag(): void
    {
        $module = $this->bootDebugModule();

        // Persist data for every panel that surfaces toolbar chips so the controller's panel iteration runs end-to-end.
        $this->writeSnapshot(
            $module,
            'tag-toolbar-ok',
            [
                'config' => [],
                'db' => ['messages' => []],
                'log' => ['messages' => []],
                'request' => ['method' => 'GET'],
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
    }

    public function testActionToolbarDataSwallowsAssetManagerFailure(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-asset-fail', []);

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

    public function testActionToolbarRendersErrorBlockForPanelWithCapturedException(): void
    {
        $module = $this->bootDebugModule();

        $error = new FlattenException(new \RuntimeException('Boom'));

        $this->writeSnapshotWithExceptions($module, 'tag-toolbar-error', ['request' => $error]);

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionToolbar('tag-toolbar-error');

        self::assertStringContainsString(
            'toolbar-label-error',
            $html,
            'Toolbar must render the error chip.',
        );
    }

    public function testActionToolbarRendersPartialForKnownTag(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-toolbar-partial', ['request' => ['statusCode' => 200]]);

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        $html = $controller->actionToolbar('tag-toolbar-partial');

        self::assertNotSame(
            '',
            $html,
            'Toolbar partial must render markup.',
        );
    }

    public function testActionViewFallsBackToFirstTagWhenTagIsNull(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-view-first', ['log' => ['messages' => []]]);

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

        $this->writeSnapshot($module, 'tag-view-panel', ['request' => ['method' => 'GET']]);

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

        // Persist an exception on the 'request' panel via the 'exceptions' channel of the snapshot.
        $error = new FlattenException(new \RuntimeException('Boom'));

        $this->writeSnapshotWithExceptions($module, 'tag-view-error', ['request' => $error]);

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

        $controller->beforeAction($action); // @phpstan-ignore argument.type

        self::assertSame(
            Response::FORMAT_HTML,
            Yii::$app->response->format,
            "'beforeAction' must force the HTML response format.",
        );
    }

    public function testGetManifestCachesResultAndReloadsOnForce(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-first', []);

        $controller = new DefaultController('default', $module);

        $first = $controller->getManifest();

        self::assertArrayHasKey(
            'tag-first',
            $first,
            "Initial manifest must include 'tag-first'.",
        );

        $this->writeSnapshot($module, 'tag-second', []);

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

    public function testInvokeNormalizeManifestNarrowsRawArrayPayload(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $normalized = $this->invokeStatic(
            DefaultController::class,
            'normalizeManifest',
            [['tag-a' => ['url' => '/a']]],
        );

        self::assertSame(
            ['tag-a' => ['url' => '/a']],
            $normalized,
            'Normalized manifest must round-trip a well-shaped entry verbatim.',
        );
    }

    public function testLoadDataPopulatesSummaryWhenTagIsKnown(): void
    {
        $module = $this->bootDebugModule();

        $this->writeSnapshot($module, 'tag-load', []);

        $controller = new DefaultController('default', $module);

        $controller->loadData('tag-load');

        self::assertSame(
            'tag-load',
            $controller->summary['tag'] ?? null,
            'Loaded summary must echo the active tag.',
        );
    }

    public function testPrimeThemeContextResolvesDarkFromGlobalCookieFallback(): void
    {
        $module = $this->bootDebugModule();

        // Query is absent and the request component's cookie collection is empty: the $_COOKIE fallback wins.
        $_COOKIE['yii-debug-toolbar-theme'] = 'dark';

        try {
            $controller = new DefaultController('default', $module);
            $context = $controller->primeThemeContext();
        } finally {
            unset($_COOKIE['yii-debug-toolbar-theme']);
        }

        self::assertSame(
            'dark',
            $context['theme'],
            '$_COOKIE fallback must seed the dark theme.',
        );
    }

    public function testPrimeThemeContextResolvesDarkFromQueryParam(): void
    {
        $module = $this->bootDebugModule();

        $_GET['yii_debug_theme'] = 'dark';

        $controller = new DefaultController('default', $module);

        $context = $controller->primeThemeContext();

        self::assertSame(
            'dark',
            $context['theme'],
            "'yii_debug_theme=dark' must select the dark theme.",
        );
        self::assertNotSame(
            '',
            $context['sun'],
            'Sun glyph must surface as SVG markup.',
        );
        self::assertNotSame(
            '',
            $context['moon'],
            'Moon glyph must surface as SVG markup.',
        );
    }

    public function testPrimeThemeContextResolvesLightThemeByDefault(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        $context = $controller->primeThemeContext();

        self::assertSame(
            'light',
            $context['theme'],
            "Missing theme inputs must default to 'light'.",
        );
    }

    public function testShellHeaderRendersPeakMemoryAndDisabledConfigChip(): void
    {
        $module = $this->bootDebugModule();

        $controller = new DefaultController('default', $module);

        Yii::$app->controller = $controller;

        // Render the shell header partial directly to exercise the 'peakMemory !== null' branch and the
        // 'configUrl === null' (disabled config chip) branch in one pass.
        $html = $controller->renderPartial(
            '_shell_header',
            [
                'debugTheme' => 'light',
                'themeIconSun' => '<svg/>',
                'themeIconMoon' => '<svg/>',
                'yiiVersion' => '2.0.0',
                'phpVersion' => '8.5.0',
                'peakMemory' => '1.21 MB',
                'configUrl' => null,
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
            "Null 'configUrl' must render the disabled config chip.",
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

        // Write a manifest entry but a payload that has no 'summary' key.
        $tag = 'tag-no-summary';
        $payload = ['exceptions' => []];

        file_put_contents(
            "{$dataPath}/{$tag}.data",
            serialize($payload),
        );
        file_put_contents(
            "{$dataPath}/index.data",
            serialize([$tag => ['tag' => $tag, 'url' => 'dummy']]),
        );

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

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(
            "Unable to find debug data tagged with 'tag-rotated'.",
        );

        // 'maxRetry = 0' avoids the 'sleep(1)' loop while still exercising the retry guard.
        $controller->loadData('tag-rotated');
    }

    public function testThrowUnexpectedValueExceptionForManifestEntryWithNonArrayValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'contains an invalid entry',
        );

        $this->invokeStatic(
            DefaultController::class,
            'normalizeManifest',
            [['tag-a' => 'not-an-array']],
        );
    }

    public function testThrowUnexpectedValueExceptionForManifestEntryWithNonStringKey(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'contains an invalid entry',
        );

        $this->invokeStatic(
            DefaultController::class,
            'normalizeManifest',
            [[0 => ['url' => '/a']]],
        );
    }

    public function testThrowUnexpectedValueExceptionWhenManifestPayloadIsNotArray(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'manifest must be an array',
        );

        $this->invokeStatic(
            DefaultController::class,
            'normalizeManifest',
            ['not-an-array'],
        );
    }

    public function testThrowUnexpectedValueExceptionWhenStringKeyMapHasNonStringKey(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'non-string key',
        );

        $this->invokeStatic(
            DefaultController::class,
            'normalizeStringKeyArray',
            [[0 => 'value']],
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

        $files = glob("{$dataPath}/*.data");

        foreach ($files === false ? [] : $files as $file) {
            @unlink($file);
        }

        return $module;
    }


    /**
     * @param array<string, array<string, mixed>> $panelData Per-panel data shapes, keyed by panel id.
     */
    private function writeSnapshot(Module $module, string $tag, array $panelData): void
    {
        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            "'logTarget' must be wired by bootstrap.",
        );

        $logTarget->tag = $tag;

        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $payload = [];

        foreach ($panelData as $id => $data) {
            $payload[$id] = serialize($data);
        }

        $payload['summary'] = [
            'tag' => $tag,
            'url' => 'dummy',
            'method' => 'GET',
            'time' => 1_700_000_000.0,
            'ip' => '127.0.0.1',
            'statusCode' => 200,
        ];
        $payload['exceptions'] = [];

        file_put_contents("{$dataPath}/{$tag}.data", serialize($payload));
        file_put_contents("{$dataPath}/index.data", serialize([$tag => $payload['summary']]));
    }

    /**
     * @param array<string, FlattenException> $exceptions Per-panel exception map keyed by panel id.
     */
    private function writeSnapshotWithExceptions(Module $module, string $tag, array $exceptions): void
    {
        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            "'logTarget' must be wired by bootstrap.",
        );

        $logTarget->tag = $tag;

        $dataPath = Yii::getAlias($module->dataPath);

        @mkdir($dataPath, 0o777, true);

        $payload = [
            'summary' => [
                'tag' => $tag,
                'url' => 'dummy',
                'method' => 'GET',
                'time' => 1_700_000_000.0,
                'ip' => '127.0.0.1',
                'statusCode' => 200,
            ],
            'exceptions' => $exceptions,
        ];

        file_put_contents("{$dataPath}/{$tag}.data", serialize($payload));
        file_put_contents("{$dataPath}/index.data", serialize([$tag => $payload['summary']]));
    }
}
