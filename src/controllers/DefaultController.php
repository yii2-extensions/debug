<?php

declare(strict_types=1);

namespace yii\debug\controllers;

use Override;
use Throwable;
use Yii;
use yii\base\{Exception, InvalidConfigException, Response};
use yii\debug\helpers\{Coerce, Format, Icon};
use yii\debug\LogTarget;
use yii\debug\models\search\DebugSearch;
use yii\debug\Panel;
use yii\debug\panels\{ConfigPanel, MailPanel};
use yii\debug\storage\{ExceptionSnapshot, RequestSummary};
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\{SidebarDataNormalizer, SidebarView};
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\{Controller, NotFoundHttpException};

use function array_key_first;
use function is_file;
use function is_string;
use function mb_strpos;
use function rtrim;
use function sleep;
use function strtolower;

/**
 * Browses recorded debug entries and serves the debug toolbar payload.
 *
 * Hosts the entry-point actions consumed by the debug UI (`index`, `view`, `toolbar-data`, `php-info`, `download-mail`)
 * and adopts external panel actions through {@see actions()}. Loads the active tag's payload into registered panels via
 * {@see loadData()} before rendering.
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
     * Summary metadata for the active debug entry.
     */
    public RequestSummary|null $summary = null;

    /**
     * @var array<string, RequestSummary>|null Cached manifest of available debug entries, indexed by tag.
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

        if (
            mb_strpos($file, '\\') !== false
            || mb_strpos($file, '/') !== false
            || !is_file($filePath)
        ) {
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

        $cursor = Coerce::string(Yii::$app->getRequest()->get('cursor'));

        $this->prepareIndexShell($manifest, $cursor);

        return $this->render(
            'index',
            [
                'dataProvider' => $dataProvider,
                'manifest' => $manifest,
                'panels' => $this->module->panels,
                'searchModel' => $searchModel,
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
        $this->view->params['debugShell'] = $this->createBareShellContext();

        return $this->render('phpinfo');
    }

    /**
     * Adopts every panel-declared action so they share the debug controller's lifecycle.
     *
     * @return array<array-key, array{class: class-string, ...}|class-string> Action map indexed by action ID.
     */
    #[Override]
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
        } catch (Throwable) {
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

        $this->prepareShell($activePanel, $tag);

        return $this->render(
            'view',
            ['activePanel' => $activePanel],
        );
    }

    /**
     * Forces the response format to HTML before delegating to the parent guard.
     *
     * @throws BadRequestHttpException When the parent guard rejects the request.
     */
    #[Override]
    public function beforeAction($action): bool
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;

        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->view->params['debugShell'] = $this->createBareShellContext();

        return true;
    }

    /**
     * Returns the debug entry manifest, reloading it from the log target on demand.
     *
     * @param bool $forceReload `true` to bypass the in-memory cache and re-read the manifest from disk.
     *
     * @return array<string, RequestSummary> Manifest entries indexed by tag.
     */
    public function getManifest(bool $forceReload = false): array
    {
        if ($this->manifest === null || $forceReload) {
            if ($forceReload) {
                clearstatcache();
            }

            $this->manifest = $this->getLogTarget()->loadManifest();
        }

        return $this->manifest;
    }

    /**
     * Loads the debug entry for the given tag into every registered panel.
     *
     * Retries up to `$maxRetry` times (waiting one second between attempts) because debug data is logged from a PHP
     * shutdown function whose execution may be delayed (notably when xdebug is enabled).
     *
     * @see https://github.com/yiisoft/yii2/issues/1504
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
                $summary = $this->getLogTarget()->loadTagToPanels($tag);

                if ($summary === null) {
                    throw new NotFoundHttpException(
                        "Debug data tagged with '$tag' does not contain summary data.",
                    );
                }

                $this->summary = $summary;

                return;
            }

            sleep(1);
        }

        throw new NotFoundHttpException(
            "Unable to find debug data tagged with '$tag'.",
        );
    }

    /**
     * Prepares the shared shell for a request panel or nested panel action.
     */
    public function prepareShell(Panel $activePanel, string $tag): void
    {
        $manifest = $this->getManifest();

        $sidebar = SidebarDataNormalizer::fromView(
            $this->module->panels,
            $manifest,
            $activePanel,
            $tag,
            $this->summary ?? throw new NotFoundHttpException(
                "Debug data tagged with '$tag' has no summary.",
            ),
        );

        $this->view->params['debugShell'] = $this->createShellContext(
            ShellContext::MODE_VIEW,
            $manifest,
            $tag,
            $this->summary,
            $sidebar,
        );
    }

    /**
     * Builds the bare shell installed by {@see beforeAction()}.
     */
    private function createBareShellContext(): ShellContext
    {
        $theme = $this->resolveTheme();

        return new ShellContext(
            mode: ShellContext::MODE_BARE,
            useShell: false,
            title: $this->module->htmlTitle(),
            debugThemeAttributes: ['lang' => 'en', 'data-yii-debug-theme' => $theme],
            resolvedTheme: $theme,
            themeIconSun: '',
            themeIconMoon: '',
            yiiVersion: '',
            phpVersion: '',
            peakMemory: null,
            configUrl: null,
            sidebar: null,
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    private function createShellContext(
        string $mode,
        array $manifest,
        string|null $activeTag,
        RequestSummary|null $summary,
        SidebarView $sidebar,
    ): ShellContext {
        $theme = $this->resolveTheme();

        $configPanel = $this->module->panels['config'] ?? null;

        $yiiVersion = $configPanel instanceof ConfigPanel ? $configPanel->getYiiVersion() : null;
        $phpVersion = $configPanel instanceof ConfigPanel ? $configPanel->getPhpVersion() : null;

        $configTag = $activeTag ?? array_key_first($manifest);
        $configUrl = $configTag === null
            ? null
            : Url::to(
                [
                    '/' . $this->module->getUniqueId() . '/default/view',
                    'panel' => 'config',
                    'tag' => $configTag,
                ],
            );

        $peakMemory = $summary?->peakMemory;

        $peakMemory = $peakMemory !== null ? Format::bytesToMb($peakMemory) : null;

        return new ShellContext(
            mode: $mode,
            useShell: true,
            title: $this->module->htmlTitle(),
            debugThemeAttributes: ['lang' => 'en', 'data-yii-debug-theme' => $theme],
            resolvedTheme: $theme,
            themeIconSun: Icon::render('sun'),
            themeIconMoon: Icon::render('moon'),
            yiiVersion: $yiiVersion ?? Yii::getVersion(),
            phpVersion: $phpVersion ?? PHP_VERSION,
            peakMemory: $peakMemory,
            configUrl: $configUrl,
            sidebar: $sidebar,
        );
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
     * @param array<string, RequestSummary> $manifest
     */
    private function prepareIndexShell(array $manifest, string $cursor): void
    {
        $sidebar = SidebarDataNormalizer::fromIndex($this->module->panels, $manifest, $cursor);

        $this->view->params['debugShell'] = $this->createShellContext(
            ShellContext::MODE_INDEX,
            $manifest,
            null,
            null,
            $sidebar,
        );
    }

    /**
     * Renders a JSON-safe panel exception snapshot through Yii's exception view.
     *
     * `ErrorHandler::renderFile()` accepts mixed params, so the immutable exception snapshot can use the view's
     * duck-typed `$exception` slot without recreating or executing the original throwable.
     *
     * @param ExceptionSnapshot $error Exception captured by the panel, typically via {@see Panel::getError()}.
     *
     * @throws InvalidConfigException When the application is not bound to a `yii\web\ErrorHandler`.
     *
     * @return string Rendered exception view body.
     */
    private function renderPanelError(ExceptionSnapshot $error): string
    {
        $errorHandler = Yii::$app->errorHandler;

        Yii::$app->getResponse()->setStatusCode(500);

        return $errorHandler->renderFile(
            '@yii/views/errorHandler/exception.php',
            ['exception' => $error],
        );
    }

    /**
     * Resolves the effective light or dark theme.
     */
    private function resolveTheme(): string
    {
        $request = Yii::$app->getRequest();

        $raw = $request->getCookies()->getValue('yii-debug-toolbar-theme');

        if ($raw === null && isset($_COOKIE['yii-debug-toolbar-theme'])) {
            $raw = $_COOKIE['yii-debug-toolbar-theme'];
        }

        $raw ??= $request->get('yii_debug_theme');

        return is_string($raw) && strtolower($raw) === 'dark'
            ? 'dark'
            : 'light';
    }
}
