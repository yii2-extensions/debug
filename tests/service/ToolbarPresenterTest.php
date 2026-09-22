<?php

declare(strict_types=1);

namespace yii\debug\tests\service;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{InvalidConfigException, View as BaseView};
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module, ToolbarRenderer};
use yii\debug\service\ToolbarPresenter;
use yii\debug\tests\support\ModuleTestCase;
use yii\helpers\Url;
use yii\web\{AssetManager, ErrorHandlerRenderEvent, View};

use function html_entity_decode;
use function json_decode;
use function ob_get_clean;
use function ob_start;
use function preg_match;
use function strpos;

/**
 * Unit tests for {@see ToolbarPresenter} rendering the debug toolbar and writing the debug response headers.
 */
#[Group('service')]
final class ToolbarPresenterTest extends ModuleTestCase
{
    public function testHtmlEmitsTheCustomElementWithTheCapturedTagAndDefaults(): void
    {
        $module = $this->bootstrappedModule();

        $html = (new ToolbarPresenter($module))->html();

        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $html,
            'Custom element marker must be present.',
        );
        self::assertStringContainsString(
            "tag={$this->logTarget($module)->tag}",
            $html,
            'Payload URL must carry the captured tag.',
        );
        self::assertStringContainsString(
            'data-position="bottom"',
            $html,
            'Default position must be bottom.',
        );
        self::assertStringContainsString(
            'data-height="50"',
            $html,
            'Default height percentage must be `50`.',
        );
    }

    public function testHtmlKeepsOnlyRoutableSkipAjaxRequestUrlEntries(): void
    {
        $module = $this->bootstrappedModule();

        $module->skipAjaxRequestUrl = [
            'array-route' => ['/healthcheck'],
            'string-route' => 'site/index',
            'ignored' => 42,
        ];

        self::assertSame(
            [Url::to(['/healthcheck']), Url::to('site/index')],
            $this->skipUrls((new ToolbarPresenter($module))->html()),
            'Only `string` and `array` routes may reach the skip list.',
        );
    }

    public function testHtmlReadsThePositionAndHeightConfiguredOnTheModule(): void
    {
        $module = $this->bootstrappedModule();

        $module->toolbarPosition = 'upper';
        $module->defaultHeight = 75;

        $html = (new ToolbarPresenter($module))->html();

        self::assertStringContainsString(
            'data-position="upper"',
            $html,
            'Configured position must reach the element.',
        );
        self::assertStringContainsString(
            'data-height="75"',
            $html,
            'Configured height must reach the element.',
        );
    }

    public function testInjectIntoErrorPageAppendsWhenTheBodyMarkerIsMissing(): void
    {
        $module = $this->bootstrappedModule();

        $event = new ErrorHandlerRenderEvent();

        $event->output = 'plain text error';

        (new ToolbarPresenter($module))->injectIntoErrorPage($event);

        self::assertStringContainsString(
            'plain text error',
            $event->output,
            'Original output must be preserved.',
        );
        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $event->output,
            'Toolbar markup must be appended.',
        );
    }

    public function testInjectIntoErrorPageInsertsTheToolbarBeforeItsRuntimeScript(): void
    {
        $module = $this->bootstrappedModule();

        $event = new ErrorHandlerRenderEvent();

        $event->output = '<html><body>boom</body></html>';

        (new ToolbarPresenter($module))->injectIntoErrorPage($event);

        self::assertStringContainsString(
            '<script type="module"',
            $event->output,
            'Runtime script must load as an ES module.',
        );
        self::assertTrue(
            strpos($event->output, '<yii-debug-toolbar') < strpos($event->output, '<script type="module"'),
            'Order: markup before script.',
        );
    }

    public function testRenderEchoesTheToolbarFollowedByItsRuntimeScript(): void
    {
        $module = $this->bootstrappedModule();

        Yii::$app->setModule('debug', $module);

        ob_start();
        (new ToolbarPresenter($module))->render(new View());
        $output = (string) ob_get_clean();

        self::assertStringContainsString(
            '<yii-debug-toolbar',
            $output,
            'Toolbar markup must be echoed.',
        );
        self::assertTrue(
            strpos($output, '<yii-debug-toolbar') < strpos($output, '<script type="module"'),
            'Order: markup before script.',
        );
    }

    public function testRendererKeepsAnExplicitView(): void
    {
        $view = new View();

        $renderer = $this->invoke(
            new ToolbarPresenter(new Module('debug')),
            'renderer',
            [$view],
        );

        self::assertInstanceOf(
            ToolbarRenderer::class,
            $renderer,
            'A renderer must be returned.',
        );
        self::assertSame(
            $view,
            $this->getInaccessibleProperty($renderer, 'view'),
            'Explicit render views must not be replaced by the application view.',
        );
    }

    public function testRendererUsesTheApplicationViewWhenNoneIsGiven(): void
    {
        $renderer = $this->invoke(
            new ToolbarPresenter(new Module('debug')),
            'renderer',
        );

        self::assertInstanceOf(
            ToolbarRenderer::class,
            $renderer,
            'A renderer must be returned.',
        );
        self::assertSame(
            Yii::$app->getView(),
            $this->getInaccessibleProperty($renderer, 'view'),
            'Application view must be the fallback.',
        );
    }

    public function testRenderUsesTheRendererASubclassProvides(): void
    {
        $module = $this->bootstrappedModule();

        Yii::$app->setModule('debug', $module);

        $presenter = new class ($module) extends ToolbarPresenter {
            protected function renderer(BaseView|null $view = null): ToolbarRenderer
            {
                return new ToolbarRenderer(
                    $view ?? Yii::$app->getView(),
                    new AssetManager(
                        [
                            'basePath' => '@runtime/assets',
                            'baseUrl' => '/subclass-assets',
                        ],
                    ),
                    Module::VIEW_PATH_ALIAS,
                );
            }
        };

        ob_start();
        $presenter->render(new View());
        $output = (string) ob_get_clean();

        self::assertStringContainsString(
            'src="/subclass-assets/',
            $output,
            'Runtime script must be published by the overridden renderer.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenTheToolbarHtmlIsBuiltBeforeBootstrap(): void
    {
        $presenter = new ToolbarPresenter(new Module('debug'));

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_NOT_BOOTSTRAPPED->getMessage(),
        );

        $presenter->html();
    }

    public function testWriteDebugHeadersSetsTheTagDurationAndFullViewLink(): void
    {
        $module = $this->bootstrappedModule();

        $_SERVER['REQUEST_TIME_FLOAT'] = 1000.0;

        MockerState::addCondition(
            'yii\\debug\\service',
            'microtime',
            [true],
            1000.5,
            true,
        );

        $response = Yii::$app->getResponse();

        (new ToolbarPresenter($module))->writeDebugHeaders($response);

        $tag = $this->logTarget($module)->tag;
        $headers = $response->getHeaders();

        self::assertSame(
            [
                'tag' => $tag,
                'duration' => '500.000',
                'link' => Url::toRoute(['/debug/view', 'tag' => $tag]),
            ],
            [
                'tag' => $headers->get('X-Debug-Tag'),
                'duration' => $headers->get('X-Debug-Duration'),
                'link' => $headers->get('X-Debug-Link'),
            ],
            'Exact tag, duration, and link values must be written.',
        );
    }

    public function testWriteDebugHeadersUsesTheCurrentTimeWhenTheRequestStartIsUnusable(): void
    {
        $module = $this->bootstrappedModule();

        $_SERVER['REQUEST_TIME_FLOAT'] = 'not-a-timestamp';

        MockerState::addCondition(
            'yii\\debug\\service',
            'microtime',
            [true],
            1000.5,
            true,
        );

        $response = Yii::$app->getResponse();

        (new ToolbarPresenter($module))->writeDebugHeaders($response);

        self::assertSame(
            '0.000',
            $response->getHeaders()->get('X-Debug-Duration'),
            'Unusable request start must fall back to the current time.',
        );
    }

    /**
     * Builds a bootstrapped debug module with a silenced logger.
     */
    private function bootstrappedModule(): Module
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $module->bootstrap(Yii::$app);

        $this->silenceLogger();

        return $module;
    }

    /**
     * Returns the log target the bootstrap coerced onto the module.
     */
    private function logTarget(Module $module): LogTarget
    {
        self::assertInstanceOf(
            LogTarget::class,
            $module->logTarget,
            'Bootstrap must coerce the log target.',
        );

        return $module->logTarget;
    }

    /**
     * Extracts the skip-URL list the rendered toolbar element carries, verbatim.
     *
     * @return array<array-key, mixed> Decoded skip-URL entries, in element order.
     */
    private function skipUrls(string $html): array
    {
        self::assertSame(
            1,
            preg_match("/data-skip-urls='([^']*)'/", $html, $matches),
            'Skip-URL attribute must be rendered.',
        );

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        self::assertIsArray(
            $decoded,
            'Skip-URL attribute must hold a JSON array.',
        );

        return $decoded;
    }
}
