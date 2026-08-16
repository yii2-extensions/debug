<?php

declare(strict_types=1);

namespace yii\debug;

use yii\base\View;
use yii\helpers\Html;
use yii\web\AssetManager;

use function strripos;
use function substr_replace;
use function trim;

/**
 * Renders shared toolbar views and resolves their assets through Yii2.
 */
final readonly class ToolbarRenderer
{
    /**
     * @param View $view Yii2 PHP view renderer.
     * @param AssetManager $assetManager Yii2 asset publisher.
     * @param string $viewPath Shared toolbar template path or alias.
     */
    public function __construct(
        private View $view,
        private AssetManager $assetManager,
        private string $viewPath,
    ) {}

    /**
     * Injects toolbar markup immediately before the final closing body tag.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->inject($html, $toolbar);
     * ```
     *
     * @param string $html Response HTML.
     * @param string $toolbar Rendered toolbar markup.
     *
     * @return string HTML containing the toolbar, appended when no closing body tag exists.
     */
    public function inject(string $html, string $toolbar): string
    {
        $offset = strripos($html, '</body>');

        return $offset === false
            ? "{$html}{$toolbar}"
            : substr_replace($html, $toolbar, $offset, 0);
    }

    /**
     * Renders the framework-neutral toolbar template through the Yii2 view component.
     *
     * Usage example:
     *
     * ```php
     * $element = $renderer->renderElement('/debug/default/toolbar-data?tag=request-1');
     * ```
     *
     * @param string $dataUrl Toolbar payload URL.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     *
     * @return string Rendered toolbar custom element.
     */
    public function renderElement(
        string $dataUrl,
        array $skipUrls = [],
        string $position = 'bottom',
        int $height = 50,
    ): string {
        return trim(
            $this->view->renderFile(
                "{$this->viewPath}/toolbar.php",
                [
                    'dataUrl' => $dataUrl,
                    'height' => $height,
                    'position' => $position,
                    'skipUrls' => $skipUrls,
                ],
            ),
        );
    }

    /**
     * Renders a script tag for the Yii2-published toolbar runtime.
     *
     * Usage example:
     *
     * ```php
     * $script = $renderer->scriptTag();
     * ```
     *
     * @return string Toolbar runtime script tag.
     */
    public function scriptTag(): string
    {
        $bundle = $this->assetManager->getBundle(ToolbarAsset::class);
        $url = $this->assetManager->getAssetUrl($bundle, 'dist/js/toolbar.min.js');

        return Html::jsFile($url);
    }
}
