<?php

declare(strict_types=1);

namespace yii\debug\service;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Toolbar\DebugHeader;
use Throwable;
use Yii;
use yii\base\{InvalidConfigException, View as BaseView};
use yii\debug\{Module, ToolbarRenderer};
use yii\helpers\Url;
use yii\web\{ErrorHandlerRenderEvent, Response, View};

use function is_array;
use function is_string;
use function number_format;

/**
 * Renders the debug toolbar into a page and reports the captured request through the debug response headers.
 *
 * The caller decides whether the current request may see the toolbar; every method here assumes that decision was
 * already made.
 */
class ToolbarPresenter
{
    /**
     * @param Module $module Debug module read for the toolbar configuration at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Returns the toolbar HTML: a `<yii-debug-toolbar>` custom element wired with data attributes the bundled JS
     * reads.
     *
     * @throws InvalidConfigException when the module has not been bootstrapped.
     *
     * @return string Rendered `<yii-debug-toolbar>` element.
     */
    public function html(): string
    {
        $logTarget = $this->module->getLogTarget();

        $url = Url::toRoute(
            [
                '/' . $this->module->getUniqueId() . '/toolbar-data',
                'tag' => $logTarget->tag,
            ],
        );

        $skipAjaxRequestUrl = [];

        foreach ($this->module->skipAjaxRequestUrl as $route) {
            if (is_string($route) || is_array($route)) {
                $skipAjaxRequestUrl[] = Url::to($route);
            }
        }

        return $this->renderer()->renderElement(
            dataUrl: $url,
            skipUrls: $skipAjaxRequestUrl,
            position: $this->module->toolbarPosition,
            height: $this->module->defaultHeight,
        );
    }

    /**
     * Rewrites the rendered HTML of an error page so it carries the toolbar and its runtime script.
     *
     * @param ErrorHandlerRenderEvent $event Render event carrying the error-page HTML.
     *
     * @throws InvalidConfigException when the module has not been bootstrapped.
     */
    public function injectIntoErrorPage(ErrorHandlerRenderEvent $event): void
    {
        $renderer = $this->renderer();
        $injection = $this->module->getToolbarHtml() . $renderer->scriptTag();

        $event->output = $renderer->inject($event->output, $injection);
    }

    /**
     * Echoes the toolbar and its runtime script at the end of the page body.
     *
     * The toolbar markup is rendered dynamically, so a cached page still carries the tag of the request that served
     * it, while the runtime URL is resolved through the Yii2 asset manager.
     *
     * @param View $view View handling the current response.
     *
     * @throws Throwable when the view dynamic render fails for the current request.
     */
    public function render(View $view): void
    {
        echo $view->renderDynamic(
            'return Yii::$app->getModule("' . $this->module->getUniqueId() . '")->getToolbarHtml();',
        );

        echo $this->renderer($view)->scriptTag();
    }

    /**
     * Sets the tag, duration, and full-view link of the captured request on the response.
     *
     * @param Response $response Response about to be sent.
     *
     * @throws InvalidConfigException when the module has not been bootstrapped.
     */
    public function writeDebugHeaders(Response $response): void
    {
        $logTarget = $this->module->getLogTarget();

        $route = $this->module->getUniqueId();

        $url = Url::toRoute(
            [
                "/{$route}/view",
                'tag' => $logTarget->tag,
            ],
        );

        $rawStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $requestStart = Coerce::floatOrNull($rawStart) ?? microtime(true);

        $response->getHeaders()
            ->set(DebugHeader::TAG->value, $logTarget->tag)
            ->set(
                DebugHeader::DURATION->value,
                number_format((microtime(true) - $requestStart) * 1000, 3, '.', ''),
            )
            ->set(DebugHeader::LINK->value, $url);
    }

    /**
     * Creates the Yii2-specific toolbar renderer.
     *
     * Override point: a subclass returning its own renderer changes the templates and the asset publisher every
     * method here draws the toolbar markup and its runtime script from.
     *
     * @param BaseView|null $view View handling the current response, or `null` to use the application view.
     *
     * @return ToolbarRenderer Configured toolbar renderer.
     */
    protected function renderer(BaseView|null $view = null): ToolbarRenderer
    {
        $view ??= Yii::$app->getView();

        return new ToolbarRenderer($view, Yii::$app->getAssetManager(), Module::VIEW_PATH_ALIAS);
    }
}
