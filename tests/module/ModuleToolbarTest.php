<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{Event, InvalidConfigException};
use yii\caching\FileCache;
use yii\debug\actions\ToolbarDataAction;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module, ToolbarRenderer};
use yii\debug\tests\support\ModuleTestCase;
use yii\web\{Response, View};

/**
 * Unit tests for {@see Module} covering toolbar HTML defaults and skip URLs, custom module IDs, request-cache behavior,
 * access/sender guards, pre-bootstrap rejection, toolbar-data payloads, and explicit renderer views.
 */
#[Group('module')]
final class ModuleToolbarTest extends ModuleTestCase
{
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

    public function testRenderToolbarHonorsCustomModuleId(): void
    {
        $moduleId = 'my_debug';

        $module = new Module($moduleId);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule(
            $moduleId,
            $module,
        );

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

        Yii::$app->setModule(
            'debug',
            $module,
        );

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        Yii::$app->set(
            'cache',
            new FileCache(['cachePath' => '@runtime/cache']),
        );

        self::assertInstanceOf(
            FileCache::class,
            Yii::$app->getCache(),
            'Cache component must be an instance of FileCache.',
        );

        Yii::$app->getCache()->flush();

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

    public function testThrowInvalidConfigExceptionWhenToolbarHtmlBuiltBeforeBootstrap(): void
    {
        $module = new Module('debug');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_NOT_BOOTSTRAPPED->getMessage(),
        );

        $module->getToolbarHtml();
    }

    public function testToolbarDataActionExposesNewBrandKeys(): void
    {
        $this->resetDebugDataPath();

        $module = new Module(
            'debug',
            null,
            ['dataPath' => '@runtime/debug'],
        );

        $module->allowedIPs = ['*'];

        $app = Yii::$app;

        $app->setModule(
            'debug',
            $module,
        );
        $app->getRequest()->setUrl('dummy');
        $module->bootstrap($app);

        Yii::$app->log->getLogger()->messages = [];

        Yii::debug(
            'manifest-bootstrap',
        );

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

    /**
     * Clears snapshot fixtures before capturing toolbar metadata.
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
}
