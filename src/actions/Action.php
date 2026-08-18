<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Override;
use PHPForge\Debug\Helper\{Format, Icon};
use PHPForge\Debug\Storage\{ExceptionSnapshot, RequestSummary};
use Yii;
use yii\base\{InvalidConfigException, ViewContextInterface};
use yii\debug\collectors\MailCollector;
use yii\debug\{LogTarget, Module, Panel};
use yii\debug\panels\ConfigPanel;
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\{SidebarDataNormalizer, SidebarView};
use yii\helpers\Url;
use yii\web\{NotFoundHttpException, Response};

use function array_key_first;
use function dirname;
use function is_string;
use function strtolower;

/**
 * Base class for the standalone debugger actions dispatched through {@see Module::$actionMap}.
 *
 * Owns the plumbing the removed debug controller used to provide: typed module access, manifest caching, per-tag
 * snapshot loading with retries, shared shell-context preparation, layout-wrapped view rendering, and the panel
 * error view.
 */
class Action extends \yii\web\Action implements ViewContextInterface
{
    /**
     * Summary metadata for the active debug entry, populated by {@see loadData()}.
     */
    public RequestSummary|null $summary = null;

    /**
     * @var array<string, RequestSummary>|null Cached manifest of available debug entries, indexed by tag.
     */
    private array|null $manifest = null;

    /**
     * Returns the owning debug module.
     *
     * Usage example:
     *
     * ```php
     * $module = $this->getDebugModule();
     * ```
     *
     * @throws InvalidConfigException When the action was dispatched without a debug module.
     *
     * @return Module Owning debug module.
     */
    public function getDebugModule(): Module
    {
        $module = $this->getModule();

        if (!$module instanceof Module) {
            throw new InvalidConfigException(
                'Debug actions must be dispatched through the debug module.',
            );
        }

        return $module;
    }

    /**
     * Returns the debug entry manifest, reloading it from the log target on demand.
     *
     * Usage example:
     *
     * ```php
     * $manifest = $this->getManifest();
     * ```
     *
     * @param bool $forceReload `true` to bypass the in-memory cache and re-read the manifest from disk.
     *
     * @return array<string, RequestSummary> Manifest entries indexed by tag.
     */
    public function getManifest(bool $forceReload = false): array
    {
        if ($this->manifest === null || $forceReload) {
            $this->manifest = $this->getLogTarget()->loadManifest();
        }

        return $this->manifest;
    }

    /**
     * Returns the view directory shared by every debugger page.
     *
     * @return string Absolute path to the debugger views.
     */
    #[Override]
    public function getViewPath(): string
    {
        return dirname(__DIR__) . '/views/default';
    }

    /**
     * Loads the debug entry for the given tag into every registered panel.
     *
     * Retries up to `$maxRetry` times (waiting one second between attempts) because debug data is logged from a PHP
     * shutdown function whose execution may be delayed (notably when xdebug is enabled).
     *
     * Usage example:
     *
     * ```php
     * $this->loadData($tag);
     * ```
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

            if ($retry < $maxRetry) {
                // `sleep` stays unimported so `MockerExtension` can swap it in tests.
                sleep(1);
            }
        }

        throw new NotFoundHttpException(
            "Unable to find debug data tagged with '$tag'.",
        );
    }

    /**
     * Prepares the shared shell for a request panel or nested panel action.
     *
     * Usage example:
     *
     * ```php
     * $this->prepareShell($activePanel, $tag);
     * ```
     *
     * @param Panel $activePanel Panel focused by the rendered page.
     * @param string $tag Tag of the loaded debug entry.
     *
     * @throws NotFoundHttpException When no summary was loaded for the tag.
     */
    public function prepareShell(Panel $activePanel, string $tag): void
    {
        $manifest = $this->getManifest();

        $sidebar = SidebarDataNormalizer::fromView(
            $this->getDebugModule()->panels,
            $manifest,
            $activePanel,
            $tag,
            $this->summary ?? throw new NotFoundHttpException(
                "Debug data tagged with '$tag' has no summary.",
            ),
        );

        Yii::$app->getView()->params['debugShell'] = $this->createShellContext(
            ShellContext::MODE_VIEW,
            $manifest,
            $tag,
            $this->summary,
            $sidebar,
        );
    }

    /**
     * Renders a debugger view wrapped in the shared page layout.
     *
     * Usage example:
     *
     * ```php
     * return $this->render('index', ['manifest' => $manifest]);
     * ```
     *
     * @param string $view View name relative to {@see getViewPath()}.
     * @param array<string, mixed> $params View parameters.
     *
     * @return string Rendered page markup.
     */
    public function render(string $view, array $params = []): string
    {
        $content = $this->renderPartial($view, $params);

        return Yii::$app->getView()->renderFile(
            $this->getDebugModule()->getLayoutPath() . '/main.php',
            ['content' => $content],
            $this,
        );
    }

    /**
     * Renders a debugger view without the page layout.
     *
     * Usage example:
     *
     * ```php
     * return $this->renderPartial('queue-job', ['record' => $record]);
     * ```
     *
     * @param string $view View name relative to {@see getViewPath()}.
     * @param array<string, mixed> $params View parameters.
     *
     * @return string Rendered view markup.
     */
    public function renderPartial(string $view, array $params = []): string
    {
        return Yii::$app->getView()->render($view, $params, $this);
    }

    /**
     * Forces the HTML response format and installs the bare shell before the action body runs.
     */
    #[Override]
    protected function beforeRun()
    {
        Yii::$app->getResponse()->format = Response::FORMAT_HTML;

        Yii::$app->getView()->params['debugShell'] = $this->createBareShellContext();

        return true;
    }

    /**
     * Builds the bare shell installed by {@see beforeRun()}.
     */
    protected function createBareShellContext(): ShellContext
    {
        $theme = $this->resolveTheme();

        return new ShellContext(
            mode: ShellContext::MODE_BARE,
            useShell: false,
            title: $this->getDebugModule()->htmlTitle(),
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
     * Builds the shared shell for an index or view page.
     *
     * @param string $mode One of the {@see ShellContext} mode constants.
     * @param array<string, RequestSummary> $manifest Manifest entries indexed by tag.
     * @param string|null $activeTag Tag focused by the page, or `null` on the index.
     * @param RequestSummary|null $summary Summary of the focused entry, or `null` on the index.
     * @param SidebarView $sidebar Prepared sidebar payload.
     */
    protected function createShellContext(
        string $mode,
        array $manifest,
        string|null $activeTag,
        RequestSummary|null $summary,
        SidebarView $sidebar,
    ): ShellContext {
        $theme = $this->resolveTheme();
        $module = $this->getDebugModule();

        $configPanel = $module->panels['config'] ?? null;

        $yiiVersion = $configPanel instanceof ConfigPanel ? $configPanel->getYiiVersion() : null;
        $phpVersion = $configPanel instanceof ConfigPanel ? $configPanel->getPhpVersion() : null;

        $configTag = $activeTag ?? array_key_first($manifest);
        $configUrl = $configTag === null
            ? null
            : Url::to(Module::route('view', ['panel' => 'config', 'tag' => $configTag]));

        $peakMemory = $summary?->peakMemory;

        $peakMemory = $peakMemory !== null ? Format::bytesToMb($peakMemory) : null;

        return new ShellContext(
            mode: $mode,
            useShell: true,
            title: $module->htmlTitle(),
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
     * @throws InvalidConfigException When the module was not bootstrapped before the action is used.
     *
     * @return LogTarget Log target used to read the manifest and per-tag panel payloads.
     */
    protected function getLogTarget(): LogTarget
    {
        $logTarget = $this->getDebugModule()->logTarget;

        if (!$logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                'The debug module logTarget must be initialized before loading debug data.',
            );
        }

        return $logTarget;
    }

    /**
     * Returns the registered mail collector.
     *
     * @throws NotFoundHttpException When no mail collector is registered on the module.
     *
     * @return MailCollector Mail collector used to resolve captured mail files.
     */
    protected function getMailCollector(): MailCollector
    {
        $collector = $this->getDebugModule()->getCollectorCoordinator()->collector('mail');

        if (!$collector instanceof MailCollector) {
            throw new NotFoundHttpException(
                'Mail collector not found.',
            );
        }

        return $collector;
    }

    /**
     * Returns a registered panel by ID.
     *
     * @param string $id Panel ID to resolve.
     *
     * @throws NotFoundHttpException When the module has no panel registered under the given ID.
     *
     * @return Panel Panel instance matching the given ID.
     */
    protected function getPanel(string $id): Panel
    {
        $panel = $this->getDebugModule()->panels[$id] ?? null;

        if (!$panel instanceof Panel) {
            throw new NotFoundHttpException(
                "Debug panel '{$id}' not found.",
            );
        }

        return $panel;
    }

    /**
     * Prepares the shared shell for the request history page.
     *
     * @param array<string, RequestSummary> $manifest Manifest entries indexed by tag.
     * @param string $cursor Active history-cursor tag, or `''` when none is selected.
     */
    protected function prepareIndexShell(array $manifest, string $cursor): void
    {
        $sidebar = SidebarDataNormalizer::fromIndex($this->getDebugModule()->panels, $manifest, $cursor);

        Yii::$app->getView()->params['debugShell'] = $this->createShellContext(
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
     * @return string Rendered exception view body.
     */
    protected function renderPanelError(ExceptionSnapshot $error): string
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
    protected function resolveTheme(): string
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
