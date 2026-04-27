<?php

declare(strict_types=1);

namespace yii\debug\controllers;

use ReflectionMethod;
use UnexpectedValueException;
use Yii;
use yii\base\{Exception, InvalidConfigException, Response};
use yii\debug\{FlattenException, LogTarget};
use yii\debug\models\search\Debug;
use yii\debug\Panel;
use yii\debug\panels\{ConfigPanel, MailPanel};
use yii\helpers\Url;
use yii\web\{Controller, NotFoundHttpException};

use function is_array;
use function is_scalar;
use function is_string;

/**
 * Debugger controller provides browsing over available debug logs.
 *
 * @template T of \yii\debug\Module
 * @extends Controller<T>
 */
class DefaultController extends Controller
{
    /**
     * @var false|string|null Layout name for rendering views.
     */
    public $layout = 'main';
    /**
     * Module instance.
     */
    public $module = null;
    /**
     * @var array<string, mixed> Summary data (for example, URL, time)
     */
    public $summary = [];

    /**
     * @var array<string, array<string, mixed>>|null Manifest of available debug data.
     */
    private $manifest = null;

    /**
     * Download mail file action.
     *
     * @throws NotFoundHttpException if the mail file is not found or invalid.
     *
     * @return Response Response containing the mail file for download, or a console response if run in a
     * console application.
     */
    public function actionDownloadMail(string $file): Response
    {
        $mailPanel = $this->getMailPanel();

        $filePath = Yii::getAlias($mailPanel->mailPath) . '/' . basename($file);

        if ((mb_strpos($file, '\\') !== false || mb_strpos($file, '/') !== false) || !is_file($filePath)) {
            throw new NotFoundHttpException(
                'Mail file not found',
            );
        }

        return Yii::$app->response->sendFile($filePath);
    }

    /**
     * Index action, shows list of available debug data.
     *
     * @throws NotFoundHttpException if no debug data is available.
     *
     * @return string Rendered index view.
     */
    public function actionIndex(): string
    {
        $searchModel = new Debug();

        $manifest = $this->getManifest();

        $dataProvider = $searchModel->search($_GET, array_values($manifest));

        // load latest request
        if ($manifest === []) {
            throw new Exception(
                'No debug data have been collected yet, try browsing the website first.',
            );
        }

        $tag = array_key_first($manifest);

        $this->loadData($tag);

        $themeContext = $this->primeThemeContext();

        return $this->render(
            'index',
            [
                'dataProvider' => $dataProvider,
                'manifest' => $this->getManifest(),
                'panels' => $this->module->panels,
                'searchModel' => $searchModel,
                'debugTheme' => $themeContext['theme'],
                'themeIconSun' => $themeContext['sun'],
                'themeIconMoon' => $themeContext['moon'],
            ],
        );
    }

    /**
     * Renders the full `phpinfo()` output in a standalone page (no sidebar).
     *
     * This action is intentionally kept outside the panel registry so the entry never appears on the sidebar nav it is
     * linked from the Configuration panel's CTA and opens in a new tab.
     *
     * @return string Rendered phpinfo view.
     */
    public function actionPhpInfo(): string
    {
        $this->primeThemeContext();

        return $this->render('phpinfo');
    }

    /**
     * @return array<array-key, array{class: class-string, ...}|class-string> List of external action classes or
     * configurations.
     */
    public function actions(): array
    {
        $actions = [];

        foreach ($this->module->panels as $panel) {
            foreach ($panel->actions as $id => $action) {
                $actions[$id] = $action;
            }
        }

        return $actions;
    }

    /**
     * Toolbar rendering action.
     *
     * @param string $tag Debug data tag to render the toolbar for.
     *
     * @throws NotFoundHttpException if debug data for the specified tag is not found.
     *
     * @return string Rendered toolbar partial view.
     */
    public function actionToolbar(string $tag): string
    {
        $this->loadData($tag, 5);

        return $this->renderPartial(
            'toolbar',
            [
                'tag' => $tag,
                'panels' => $this->module->panels,
                'position' => $this->module->toolbarPosition,
                'defaultHeight' => $this->module->defaultHeight,
            ],
        );
    }

    /**
     * Toolbar data action, provides metadata about the debug entry and panels for the toolbar JS app.
     *
     * @param string $tag Debug data tag to retrieve metadata for.
     *
     * @throws NotFoundHttpException if debug data for the specified tag is not found.
     *
     * @return array<string, mixed> Metadata about the debug entry and panels for the toolbar JS app.
     */
    public function actionToolbarData(string $tag): array
    {
        Yii::$app->getResponse()->format = \yii\web\Response::FORMAT_JSON;

        try {
            $this->loadData($tag, 5);
        } catch (NotFoundHttpException) {
            /**
             * Tag rotated out of history. Return a JSON 404 so the toolbar can degrade gracefully without triggering
             * the host application's HTML error page.
             */
            Yii::$app->getResponse()->setStatusCode(404);

            return ['error' => 'Debug tag not found.', 'tag' => $tag];
        }

        $items = [];

        foreach ($this->module->panels as $id => $panel) {
            $data = $panel->getToolbarData();

            if ($data === []) {
                continue;
            }

            if (!isset($data['id'])) {
                $data['id'] = $id;
            }

            if (!isset($data['title'])) {
                $data['title'] = $panel->getName();
            }

            if (!isset($data['url'])) {
                $data['url'] = $panel->getUrl();
            }

            $items[] = $data;
        }

        $configPanel = $this->module->panels['config'] ?? null;

        $yiiVersion = null;
        $phpVersion = null;

        if ($configPanel instanceof ConfigPanel && is_array($configPanel->data)) {
            $configData = self::normalizeStringKeyArray($configPanel->data);
            $yiiVersion = self::readNestedScalarAsString($configData, 'application', 'yii');
            $phpVersion = self::readNestedScalarAsString($configData, 'php', 'version');
        }

        $iconBaseUrl = '';

        try {
            $published = Yii::$app->assetManager->publish(Yii::getAlias('@yii/debug/assets'));
            $publishedUrl = $published[1] ?? null;

            if (is_string($publishedUrl)) {
                $iconBaseUrl = rtrim($publishedUrl, '/') . '/svg/';
            }
        } catch (\Throwable $e) {
            /**
             * Asset manager not configured (e.g. unit test environment) keep empty so the toolbar JS falls back to the
             * bundled PNG logo and skips chip icons.
             */
        }

        return [
            'configUrl' => $configPanel !== null
                ? Url::toRoute(['/' . $this->module->getUniqueId() . '/default/view', 'tag' => $tag, 'panel' => 'config'])
                : null,
            'defaultHeight' => $this->module->defaultHeight,
            'iconBaseUrl' => $iconBaseUrl,
            'indexUrl' => Url::toRoute(['/' . $this->module->getUniqueId() . '/default/index']),
            'items' => $items,
            'logo' => $iconBaseUrl !== ''
                ? "{$iconBaseUrl}yii.svg"
                : $this->module::getYiiLogo(),
            'logoFallback' => $this->module::getYiiLogo(),
            'phpInfoUrl' => Url::toRoute(['/' . $this->module->getUniqueId() . '/default/php-info']),
            'phpVersion' => $phpVersion,
            'position' => $this->module->toolbarPosition,
            'tag' => $tag,
            'title' => 'Yii Debugger',
            'yiiVersion' => $yiiVersion,
        ];
    }

    /**
     * View action, shows debug data for the specified tag and panel.
     *
     * @param string|null $tag Debug data tag.
     * @param string|null $panel Debug panel ID.
     *
     * @throws NotFoundHttpException if debug data not found.
     *
     * @return string Response from the panel's view or the rendered view if the panel does not provide its own response.
     *
     * @see \yii\debug\Panel
     */
    public function actionView(string|null $tag = null, string|null $panel = null): string
    {
        if ($tag === null) {
            $tag = array_key_first($this->getManifest());

            if ($tag === null) {
                throw new NotFoundHttpException(
                    'No debug data have been collected yet, try browsing the website first.',
                );
            }
        }

        $this->loadData($tag);

        if ($panel !== null && isset($this->module->panels[$panel])) {
            $activePanel = $this->module->panels[$panel];
        } else {
            $activePanel = $this->getPanel($this->module->defaultPanel);
        }

        $error = $activePanel->getError();

        if ($error !== null) {
            $this->handlePanelError($error);
        }

        $themeContext = $this->primeThemeContext();

        return $this->render(
            'view',
            [
                'activePanel' => $activePanel,
                'manifest' => $this->getManifest(),
                'panels' => $this->module->panels,
                'summary' => $this->summary,
                'tag' => $tag,
                'debugTheme' => $themeContext['theme'],
                'themeIconSun' => $themeContext['sun'],
                'themeIconMoon' => $themeContext['moon'],
            ],
        );
    }

    /**
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;

        return parent::beforeAction($action);
    }

    /**
     * Resolves the debug panel's theme + theme-toggle SVG glyphs and exposes them to the view layer.
     *
     * The resolved values are pushed into `Yii::$app->view->params['debugTheme']` so the layout can pick them up
     * without re-reading the request, and a small associative array with the same data is returned so individual
     * actions can pass the SVGs to the view as render params (avoiding inline filesystem reads in the templates).
     *
     * @return array{theme: string, sun: string, moon: string}
     */
    private function primeThemeContext(): array
    {
        $request = Yii::$app->getRequest();
        $raw = $request->get(
            'yii_debug_theme',
            $request->getCookies()->getValue('yii-debug-toolbar-theme'),
        );
        $theme = is_string($raw) && strtolower($raw) === 'dark' ? 'dark' : 'light';

        $svgRoot = dirname(__DIR__) . '/assets/svg/';
        $readSvg = static function (string $name) use ($svgRoot): string {
            $path = $svgRoot . $name;
            return is_file($path) ? trim((string) file_get_contents($path)) : '';
        };

        $context = [
            'theme' => $theme,
            'sun' => $readSvg('sun.svg'),
            'moon' => $readSvg('moon.svg'),
        ];

        $view = $this->view;
        $view->params['debugTheme'] = $theme;
        $view->params['themeIconSun'] = $context['sun'];
        $view->params['themeIconMoon'] = $context['moon'];

        return $context;
    }

    /**
     * Loads debug data for the specified tag into panels.
     *
     * @param string $tag Debug data tag.
     * @param int $maxRetry Maximum numbers of tag retrieval attempts.
     *
     * @throws NotFoundHttpException if specified tag not found.
     */
    public function loadData(string $tag, int $maxRetry = 0): void
    {
        /**
         * Retry loading debug data because the debug data is logged in shutdown function which may be delayed in some
         * environment if xdebug is enabled.
         *
         * @link https://github.com/yiisoft/yii2/issues/1504
         */
        for ($retry = 0; $retry <= $maxRetry; ++$retry) {
            $manifest = $this->getManifest($retry > 0);

            if (isset($manifest[$tag])) {
                $data = $this->getLogTarget()->loadTagToPanels($tag);

                $summary = $data['summary'] ?? null;

                if (!is_array($summary)) {
                    throw new NotFoundHttpException(
                        "Debug data tagged with '$tag' does not contain summary data.",
                    );
                }

                $this->summary = self::normalizeStringKeyArray($summary);

                return;
            }

            sleep(1);
        }

        throw new NotFoundHttpException("Unable to find debug data tagged with '$tag'.");
    }

    /**
     * Returns the debug data manifest, optionally forcing a reload from the log target.
     *
     * @param bool $forceReload Whether to force reload the manifest from the log target, bypassing any cached version.
     *
     * @return array<string, array<string, mixed>> Debug data manifest, indexed by tag, containing metadata about
     * available debug entries.
     */
    protected function getManifest(bool $forceReload = false): array
    {
        if ($this->manifest === null || $forceReload) {
            if ($forceReload) {
                clearstatcache();
            }

            $this->manifest = self::normalizeManifest($this->getLogTarget()->loadManifest());
        }

        return $this->manifest;
    }

    /**
     * Returns the initialized debug log target.
     *
     * @throws InvalidConfigException if the module was not bootstrapped before controller use.
     *
     * @return LogTarget Debug log target instance used to load debug data and manifest.
     */
    private function getLogTarget(): LogTarget
    {
        $logTarget = $this->module->logTarget;

        if (!$logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                'The debug module logTarget must be initialized before loading debug data.',
            );
        }

        return $logTarget;
    }

    /**
     * Returns the configured mail panel.
     *
     * @throws NotFoundHttpException if the mail panel is not available.
     *
     * @return MailPanel Mail panel instance used for handling mail file downloads.
     */
    private function getMailPanel(): MailPanel
    {
        $panel = $this->module->panels['mail'] ?? null;

        if (!$panel instanceof MailPanel) {
            throw new NotFoundHttpException('Mail panel not found.');
        }

        return $panel;
    }

    /**
     * Returns a configured panel by ID.
     *
     * @throws NotFoundHttpException if the panel is not configured.
     *
     * @return Panel Debug panel instance corresponding to the specified ID.
     */
    private function getPanel(string $id): Panel
    {
        $panel = $this->module->panels[$id] ?? null;

        if (!$panel instanceof Panel) {
            throw new NotFoundHttpException("Debug panel '$id' not found.");
        }

        return $panel;
    }

    /**
     * Handles a serialized panel exception through Yii's legacy untyped error handler entry point.
     *
     * @param FlattenException $error Exception to handle, typically retrieved from a panel {@see getError()} method.
     */
    private function handlePanelError(FlattenException $error): void
    {
        /*+
         * Yii PHPDoc narrows this legacy entry point to Throwable, but the native method remains untyped and the debug
         * module stores panel errors as FlattenException instances.
         */
        (new ReflectionMethod(Yii::$app->errorHandler, 'handleException'))
            ->invoke(Yii::$app->errorHandler, $error);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeManifest(mixed $manifest): array
    {
        if (!is_array($manifest)) {
            throw new UnexpectedValueException(
                'Debug manifest must be an array.',
            );
        }

        $normalized = [];

        foreach ($manifest as $tag => $entry) {
            if (!is_string($tag) || !is_array($entry)) {
                throw new UnexpectedValueException(
                    'Debug manifest contains an invalid entry.',
                );
            }

            $normalized[$tag] = self::normalizeStringKeyArray($entry);
        }

        return $normalized;
    }

    /**
     * Normalizes an array to ensure all keys are strings, throwing an exception if a non-string key is encountered.
     *
     * @param array<array-key, mixed> $data Array to normalize, typically containing metadata or summary data from debug
     * panels or manifest entries.
     *
     * @return array<string, mixed> Normalized array with string keys, suitable for use in views and JSON responses
     * where string keys are expected.
     */
    private static function normalizeStringKeyArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new UnexpectedValueException(
                    'Debug data contains a non-string key.',
                );
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Reads a nested scalar value from an array and returns it as a string, or null if the value is not a scalar or the
     * specified keys do not exist.
     *
     * @param array<string, mixed> $data Array to read from, typically containing normalized summary or configuration
     * data from a debug panel.
     * @param string $section Top-level key to access in the array.
     * @param string $key Nested key to access within the specified section.
     *
     * @return string|null Scalar value at the specified nested location as a `string`, or `null` if the value is not a
     * scalar or the keys do not exist, allowing for safe retrieval of metadata without risking type errors in views or
     * JSON responses.
     */
    private static function readNestedScalarAsString(array $data, string $section, string $key): string|null
    {
        $sectionData = $data[$section] ?? null;

        if (!is_array($sectionData)) {
            return null;
        }

        $value = $sectionData[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
