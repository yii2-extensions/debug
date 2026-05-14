<?php

declare(strict_types=1);

namespace yii\debug\controllers;

use UnexpectedValueException;
use Yii;
use yii\base\{Exception, InvalidConfigException, Response};
use yii\debug\{FlattenException, LogTarget};
use yii\debug\helpers\Icon;
use yii\debug\models\search\DebugSearch;
use yii\debug\Panel;
use yii\debug\panels\{ConfigPanel, MailPanel};
use yii\helpers\Url;
use yii\web\{Controller, NotFoundHttpException};

use function is_array;
use function is_string;

/**
 * Browses recorded debug entries and serves the debug toolbar payload.
 *
 * Hosts the entry-point actions consumed by the debug UI (`index`, `view`, `toolbar`, `toolbar-data`, `php-info`,
 * `download-mail`) and adopts external panel actions through {@see actions()}. Loads the active tag's payload into
 * registered panels via {@see loadData()} before rendering.
 *
 * @template T of \yii\debug\Module
 * @extends Controller<T>
 */
class DefaultController extends Controller
{
    /**
     * @var false|string|null Layout name used when rendering full-page views.
     */
    public $layout = 'main';
    /**
     * Owning debug module instance.
     */
    public $module = null;
    /**
     * @var array<string, mixed> Summary metadata for the active debug entry (for example, URL and time).
     */
    public $summary = [];

    /**
     * @var array<string, array<string, mixed>>|null Cached manifest of available debug entries, indexed by tag.
     */
    private array|null $manifest = null;

    /**
     * Streams the requested captured mail file as a download.
     *
     * @throws NotFoundHttpException When the file name contains a path separator or the file does not exist on disk.
     *
     * @return Response Response that emits the mail file as an attachment.
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
     * Renders the index view listing every captured debug entry.
     *
     * @throws Exception When no debug entries have been captured yet.
     *
     * @return string Rendered index view.
     */
    public function actionIndex(): string
    {
        $searchModel = new DebugSearch();

        $manifest = $this->getManifest();

        $dataProvider = $searchModel->search($_GET, array_values($manifest));

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
     * Renders the full {@see phpinfo()} output in a standalone page (no sidebar).
     *
     * Kept outside the panel registry so the entry never appears on the sidebar nav; the Configuration panel links to
     * it via a CTA that opens in a new tab.
     *
     * @return string Rendered phpinfo view.
     */
    public function actionPhpInfo(): string
    {
        $this->primeThemeContext();

        return $this->render('phpinfo');
    }

    /**
     * Adopts every panel-declared action so they share the debug controller's lifecycle.
     *
     * @return array<array-key, array{class: class-string, ...}|class-string> Action map indexed by action ID.
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
     * Renders the floating debug toolbar partial for the given tag.
     *
     * @param string $tag Tag of the debug entry to render the toolbar for.
     *
     * @throws NotFoundHttpException When no debug entry exists for the given tag.
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
     * Returns the JSON metadata payload consumed by the toolbar JS app.
     *
     * Degrades gracefully on a rotated tag by emitting a JSON 404 instead of the host application's HTML error page.
     *
     * @param string $tag Tag of the debug entry to expose metadata for.
     *
     * @return array{error: string, tag: string}|array{
     *   configUrl: string|null,
     *   defaultHeight: int,
     *   iconBaseUrl: string,
     *   indexUrl: string,
     *   items: list<array<string, mixed>>,
     *   logo: string,
     *   logoFallback: string,
     *   phpInfoUrl: string,
     *   phpVersion: string|null,
     *   position: string,
     *   tag: string,
     *   title: string,
     *   yiiVersion: string|null,
     * } Toolbar metadata, or an error envelope when the tag has rotated out.
     */
    public function actionToolbarData(string $tag): array
    {
        Yii::$app->getResponse()->format = \yii\web\Response::FORMAT_JSON;

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

        $yiiVersion = $configPanel instanceof ConfigPanel ? $configPanel->getYiiVersion() : null;
        $phpVersion = $configPanel instanceof ConfigPanel ? $configPanel->getPhpVersion() : null;

        $iconBaseUrl = '';

        try {
            $published = Yii::$app->assetManager->publish(Yii::getAlias('@yii/debug/assets'));

            $publishedUrl = $published[1] ?? null;

            if (is_string($publishedUrl)) {
                $iconBaseUrl = rtrim($publishedUrl, '/') . '/svg/';
            }
        } catch (\Throwable $e) {
            // Asset manager not configured (for example, unit test environment) keep empty so the toolbar JS falls back
            // to the bundled PNG logo and skips chip icons.
        }

        return [
            'configUrl' => $configPanel !== null
                ? Url::toRoute(
                    [
                        '/' . $this->module->getUniqueId() . '/default/view',
                        'tag' => $tag,
                        'panel' => 'config',
                    ],
                )
                : null,
            'defaultHeight' => $this->module->defaultHeight,
            'iconBaseUrl' => $iconBaseUrl,
            'indexUrl' => Url::toRoute(
                [
                    '/' . $this->module->getUniqueId() . '/default/index',
                ]
            ),
            'items' => $items,
            'logo' => $iconBaseUrl !== ''
                ? "{$iconBaseUrl}yii.svg"
                : $this->module::getYiiLogo(),
            'logoFallback' => $this->module::getYiiLogo(),
            'phpInfoUrl' => Url::toRoute(
                [
                    '/' . $this->module->getUniqueId() . '/default/php-info',
                ],
            ),
            'phpVersion' => $phpVersion,
            'position' => $this->module->toolbarPosition,
            'tag' => $tag,
            'title' => 'Yii Debugger',
            'yiiVersion' => $yiiVersion,
        ];
    }

    /**
     * Renders the detail view for the given tag, focused on the requested panel.
     *
     * Falls back to the most recent tag when `$tag` is omitted and to the module's default panel when `$panel` is
     * omitted or unknown. Panel-reported errors are rendered through Yii's exception view instead of the panel
     * template.
     *
     * @param string|null $tag Tag of the debug entry to render, or `null` to use the most recent one.
     * @param string|null $panel Panel ID to focus, or `null` to use the module's default panel.
     *
     * @throws NotFoundHttpException When no debug entries are available or the resolved tag cannot be loaded.
     *
     * @return string Rendered panel view, or the rendered exception view when the panel reported an error.
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
            return $this->renderPanelError($error);
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
     * Forces the response format to HTML before delegating to the parent guard.
     *
     * @throws \yii\web\BadRequestHttpException When the parent guard rejects the request.
     */
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;

        return parent::beforeAction($action);
    }

    /**
     * Returns the debug entry manifest, reloading it from the log target on demand.
     *
     * @param bool $forceReload `true` to bypass the in-memory cache and re-read the manifest from disk.
     *
     * @return array<string, array<string, mixed>> Manifest entries indexed by tag.
     */
    public function getManifest(bool $forceReload = false): array
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
     * Loads the debug entry for the given tag into every registered panel.
     *
     * Retries up to `$maxRetry` times (waiting one second between attempts) because debug data is logged from a PHP
     * shutdown function whose execution may be delayed (notably when xdebug is enabled).
     *
     * @link https://github.com/yiisoft/yii2/issues/1504
     *
     * @param string $tag Tag of the debug entry to load.
     * @param int $maxRetry Maximum number of retries before giving up.
     *
     * @throws NotFoundHttpException When the tag cannot be located after every retry, or when the entry lacks a
     * summary block.
     */
    public function loadData(string $tag, int $maxRetry = 0): void
    {
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
     * Resolves the active theme and theme-toggle SVG glyphs and exposes them to the view layer.
     *
     * The resolved values are pushed into `Yii::$app->view->params['debugTheme']` so the layout can pick them up
     * without re-reading the request; the returned associative array carries the same data so individual actions can
     * pass the SVGs as render params and avoid inline filesystem reads in templates.
     *
     * @return array{theme: string, sun: string, moon: string} Theme name (`'dark'` or `'light'`) and the inline SVG
     * markup for both toggle icons.
     */
    public function primeThemeContext(): array
    {
        $request = Yii::$app->getRequest();

        $raw = $request->get('yii_debug_theme');

        if ($raw === null) {
            $raw = $request->getCookies()->getValue('yii-debug-toolbar-theme');

            if ($raw === null && isset($_COOKIE['yii-debug-toolbar-theme'])) {
                $candidate = $_COOKIE['yii-debug-toolbar-theme'];

                if (is_string($candidate)) {
                    $raw = $candidate;
                }
            }
        }

        $theme = is_string($raw) && strtolower($raw) === 'dark' ? 'dark' : 'light';

        $context = [
            'theme' => $theme,
            'sun' => Icon::render('sun'),
            'moon' => Icon::render('moon'),
        ];

        $view = $this->view;

        $view->params['debugTheme'] = $theme;
        $view->params['themeIconSun'] = $context['sun'];
        $view->params['themeIconMoon'] = $context['moon'];

        return $context;
    }

    /**
     * Returns the initialized debug log target.
     *
     * @throws InvalidConfigException When the module was not bootstrapped before the controller is used.
     *
     * @return LogTarget Log target used to read the manifest and per-tag panel payloads.
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
     * @throws NotFoundHttpException When no mail panel is registered on the module.
     *
     * @return MailPanel Mail panel used to resolve captured mail files.
     */
    private function getMailPanel(): MailPanel
    {
        $panel = $this->module->panels['mail'] ?? null;

        if (!$panel instanceof MailPanel) {
            throw new NotFoundHttpException(
                'Mail panel not found.',
            );
        }

        return $panel;
    }

    /**
     * Returns a registered panel by ID.
     *
     * @throws NotFoundHttpException When the module has no panel registered under the given ID.
     *
     * @return Panel Panel instance matching the given ID.
     */
    private function getPanel(string $id): Panel
    {
        $panel = $this->module->panels[$id] ?? null;

        if (!$panel instanceof Panel) {
            throw new NotFoundHttpException(
                "Debug panel '{$id}' not found.",
            );
        }

        return $panel;
    }

    /**
     * Narrows the manifest payload returned by the log target into a strictly typed map.
     *
     * @param mixed $manifest Raw manifest value read from the log target.
     *
     * @throws UnexpectedValueException When the payload is not an array of string-keyed entries.
     *
     * @return array<string, array<string, mixed>> Manifest entries indexed by tag.
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
     * Narrows an arbitrarily keyed array into a string-keyed map, failing fast on any non-string key.
     *
     * @param array<array-key, mixed> $data Source array (typically a manifest entry or summary block).
     *
     * @throws UnexpectedValueException When any key is not a string.
     *
     * @return array<string, mixed> Map suitable for views and JSON responses that expect string keys.
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
     * Renders a serialized panel exception through Yii's exception view.
     *
     * `ErrorHandler::renderFile()` accepts mixed params and injects `$handler` into the view, so the FlattenException
     * travels through the duck-typed `$exception` slot without tripping the `@param Throwable` PHPDoc.
     *
     * @param FlattenException $error Exception captured by the panel, typically via {@see Panel::getError()}.
     *
     * @throws InvalidConfigException When the application is not bound to a `yii\web\ErrorHandler`.
     *
     * @return string Rendered exception view body.
     */
    private function renderPanelError(FlattenException $error): string
    {
        $errorHandler = Yii::$app->errorHandler;

        Yii::$app->getResponse()->setStatusCode(500);

        return $errorHandler->renderFile(
            '@yii/views/errorHandler/exception.php',
            ['exception' => $error],
        );
    }
}
