<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Throwable;
use Yii;
use yii\debug\{Module, ToolbarDataMapper};
use yii\debug\panels\ConfigPanel;
use yii\helpers\Url;
use yii\web\{NotFoundHttpException, Response};

use function is_string;

/**
 * Returns the JSON metadata payload consumed by the toolbar JS app.
 */
class ToolbarDataAction extends Action
{
    /**
     * Runs the action.
     *
     * Degrades gracefully on a rotated tag by emitting a JSON 404 instead of the host application's HTML error page.
     *
     * @param string $tag Tag of the debug entry to expose metadata for.
     *
     * @return array{error: string, tag: string}|array{
     *   configUrl: string,
     *   defaultHeight: int,
     *   iconBaseUrl: string,
     *   indexUrl: string,
     *   items: list<array<string, mixed>>,
     *   logo: string|null,
     *   logoFallback: string|null,
     *   phpInfoUrl: string|null,
     *   phpVersion: string|null,
     *   position: string,
     *   tag: string,
     *   title: string,
     *   yiiVersion: string|null,
     * } Toolbar metadata, or an error envelope when the tag has rotated out.
     */
    public function run(string $tag): array
    {
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;

        try {
            $this->loadData($tag, 5);
        } catch (NotFoundHttpException) {
            // Tag rotated out of history. Return a JSON 404 so the toolbar can degrade gracefully without triggering
            // the host application's HTML error page.
            Yii::$app->getResponse()->setStatusCode(404);

            return [
                'error' => 'Debug tag not found.',
                'tag' => $tag,
            ];
        }

        $module = $this->getDebugModule();

        $configPanel = $module->panels['config'] ?? null;

        $yiiVersion = $configPanel instanceof ConfigPanel ? $configPanel->getYiiVersion() : null;
        $phpVersion = $configPanel instanceof ConfigPanel ? $configPanel->getPhpVersion() : null;

        $iconBaseUrl = '';

        try {
            $published = Yii::$app->assetManager->publish(Module::SOURCE_PATH);

            $publishedUrl = $published[1] ?? null;

            if (is_string($publishedUrl)) {
                $iconBaseUrl = "{$publishedUrl}/svg/";
            }
        } catch (Throwable) {
            // Asset manager not configured (for example, unit test environment) keep empty so the toolbar JS falls back
            // to inline icons.
        }

        $moduleId = $module->getUniqueId();

        $indexUrl = Url::toRoute(["/{$moduleId}/index"]);

        $configUrl = $configPanel !== null
                ? Url::toRoute(
                    [
                        "/{$moduleId}/view",
                        'tag' => $tag,
                        'panel' => 'config',
                    ],
                )
                : null;

        return (new ToolbarDataMapper())->map(
            tag: $tag,
            title: 'Yii Debugger',
            indexUrl: $indexUrl,
            configUrl: $configUrl,
            panels: $module->panels,
            position: $module->toolbarPosition,
            defaultHeight: $module->defaultHeight,
            iconBaseUrl: $iconBaseUrl,
            logo: $iconBaseUrl !== ''
                ? "{$iconBaseUrl}yii.svg"
                : $module::getYiiLogo(),
            logoFallback: $module::getYiiLogo(),
            phpInfoUrl: Url::toRoute(
                [
                    "/{$moduleId}/php-info",
                ],
            ),
            phpVersion: $phpVersion,
            yiiVersion: $yiiVersion,
        );
    }
}
