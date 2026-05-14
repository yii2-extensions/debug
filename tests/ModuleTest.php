<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\{DataProvider, Group};
use Yii;
use yii\base\Event;
use yii\caching\FileCache;
use yii\debug\controllers\DefaultController;
use yii\debug\{DebugAsset, LogTarget, Module};
use yii\web\AssetManager;
use yii\web\Response;

/**
 * Unit tests for {@see Module} covering IP-based access control, toolbar HTML/JSON rendering, the `php-info` standalone
 * action wiring, debug-asset registration, and request-cache behavior.
 *
 * {@see ModuleTest::checkAccessProvider} for IP-filter test case data providers.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('module')]
final class ModuleTest extends TestCase
{
    /**
     * @return array<int, array{0: list<string>, 1: string, 2: bool}>
     */
    public static function checkAccessProvider(): array
    {
        return [
            [[], '10.20.30.40', false],
            [['10.20.30.40'], '10.20.30.40', true],
            [['*'], '10.20.30.40', true],
            [['10.20.30.*'], '10.20.30.40', true],
            [['10.20.30.*'], '10.20.40.40', false],
            [['172.16.0.0/12'], '172.15.1.2', false],
            [['172.16.0.0/12'], '172.16.0.0', true],
            [['172.16.0.0/12'], '172.22.33.44', true],
            [['172.16.0.0/12'], '172.31.255.255', true],
            [['172.16.0.0/12'], '172.32.1.2', false],
        ];
    }

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

    /**
     * @param list<string> $allowedIPs
     */
    #[DataProvider('checkAccessProvider')]
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
            $this->invoke($module, 'checkAccess'),
            'Allowed IP filters must accept matches and reject non-matching addresses.',
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

    public function testGetToolbarHtmlEmitsCustomElementWithDataUrlAndDefaults(): void
    {
        $module = new Module('debug');

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        $html = $module->getToolbarHtml();
        $logTarget = $module->logTarget;

        self::assertInstanceOf(LogTarget::class, $logTarget, 'bootstrap() must coerce logTarget to a LogTarget instance.');
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $html,
            'Toolbar must render the custom element marker.',
        );
        self::assertStringContainsString(
            'data-url="/index.php?r=debug%2Fdefault%2Ftoolbar-data&amp;tag=' . $logTarget->tag . '"',
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

        self::assertInstanceOf(LogTarget::class, $logTarget, 'bootstrap() must coerce logTarget to a LogTarget instance.');

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

    public function testToolbarDataActionExposesNewBrandKeys(): void
    {
        $module = new Module('debug', null, ['dataPath' => '@runtime/debug']);

        $module->allowedIPs = ['*'];
        $app = Yii::$app;

        $app->setModule('debug', $module);
        $module->bootstrap($app);

        // Force a single capture cycle so the manifest has at least one entry to read back. Without this the test
        // relies on leftover state in `@runtime/debug` from earlier tests — flaky under random test ordering.
        Yii::$app->log->getLogger()->messages = [];
        Yii::debug('manifest-bootstrap');
        Yii::$app->log->getLogger()->flush(true);

        $logTarget = $module->logTarget;

        self::assertInstanceOf(LogTarget::class, $logTarget, 'bootstrap() must coerce logTarget to a LogTarget instance.');

        $manifest = $logTarget->loadManifest();

        $tag = array_key_first($manifest);

        self::assertIsString(
            $tag,
            'Manifest must expose at least one captured request tag.',
        );

        $controller = new DefaultController('default', $module);

        $data = $controller->actionToolbarData($tag);

        self::assertArrayNotHasKey('error', $data, 'toolbar-data must take the success branch for a known tag.');
        self::assertArrayHasKey('title', $data, 'Success payload must declare the title key.');
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
     * Replaces the default log dispatcher with a no-op so toolbar rendering does not flush events.
     */
    private function silenceLogger(): void
    {
        Yii::getLogger()->dispatcher = self::createStub(\yii\log\Dispatcher::class);
    }
}
