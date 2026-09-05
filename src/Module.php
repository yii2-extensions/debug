<?php

declare(strict_types=1);

namespace yii\debug;

use InvalidArgumentException;
use Override;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\{CollectorCoordinator, CollectorInterface};
use PHPForge\Debug\Helper\{Coerce, Icon, SensitiveDataRedactor};
use RuntimeException;
use Throwable;
use Yii;
use yii\base\{Action, ActionEvent, Application, BootstrapInterface, Event, InvalidConfigException, View as BaseView};
use yii\debug\actions\{
    CompareAction,
    DownloadMailAction,
    IndexAction,
    PhpInfoAction,
    ResetIdentityAction,
    SetIdentityAction,
    ToolbarDataAction,
    ViewAction,
};
use yii\debug\collectors\{
    AssetCollector,
    Collector,
    ConfigCollector,
    DbCollector,
    DumpCollector,
    EventCollector,
    InertiaCollector,
    LogCollector,
    MailCollector,
    ProfilingCollector,
    QueueCollector,
    RequestCollector,
    RouterCollector,
    UserCollector,
    ViteCollector,
};
use yii\debug\exception\Message;
use yii\debug\panels\{
    AssetPanel,
    ConfigPanel,
    DbPanel,
    DumpPanel,
    EventPanel,
    InertiaPanel,
    LogPanel,
    MailPanel,
    ProfilingPanel,
    QueuePanel,
    RequestPanel,
    RouterPanel,
    UserPanel,
    VitePanel,
};
use yii\helpers\Url;
use yii\log\{Dispatcher, Target};
use yii\rbac\BaseManager;
use yii\web\{ErrorHandler, ErrorHandlerRenderEvent, ForbiddenHttpException, Response, View};

use function array_diff_key;
use function array_unique;
use function array_values;
use function base64_encode;
use function get_parent_class;
use function is_array;
use function is_callable;
use function is_string;
use function number_format;
use function str_contains;
use function trim;

/**
 * Bootstraps the debug toolbar and the full-page debugger over the active application.
 *
 * Attaches a {@see LogTarget} to capture per-request data, registers URL rules for the debugger routes, wires the
 * toolbar/exception-page injection listeners, and instantiates the panels declared in {@see $panels} (merged on top of
 * the built-in core panels).
 */
class Module extends \yii\base\Module implements BootstrapInterface
{
    /**
     * Default {@see $traceLine} template: renders each backtrace entry as an `ide://` deep link that IDE extensions
     * resolve into "open file at line".
     */
    public const string DEFAULT_IDE_TRACELINE = '<a href="ide://open?url=file://{file}&line={line}">{text}</a>';
    /**
     * Default source path for the framework-neutral Debug Core assets. The framework adapter may override this to
     * provide a different path.
     */
    public const string SOURCE_PATH = '@vendor/php-forge/debug-core/resources/assets';
    /**
     * Adapter-owned alias for the shared Debug Core templates.
     */
    public const string VIEW_PATH_ALIAS = '@yiiDebugViews';
    /**
     * Hosts allowed to access this module. Each entry is resolved to an IP at runtime; useful for dynamic DNS.
     *
     * @var list<string>
     */
    public array $allowedHosts = [];
    /**
     * IPs allowed to access this module. Entries may be exact, wildcard (`192.168.0.*`) or CIDR (`172.16.0.0/12`).
     *
     * @var list<string>
     */
    public array $allowedIPs = ['127.0.0.1', '::1'];
    /**
     * RBAC access checker component id or fully configured manager.
     *
     * @var array<string, mixed>|BaseManager|string
     */
    public BaseManager|array|string $authManager = 'authManager';
    /**
     * Callback evaluated by {@see checkAccess()}. Receives the current {@see Action} (or `null`) and must return `true`
     * to grant access.
     *
     * @var (callable(Action|null): bool)|null
     */
    public mixed $checkAccessCallback = null;
    /**
     * Debug collectors resolved from instances, class names, or Yii configuration arrays during {@see init()}.
     *
     * Collector IDs come from {@see CollectorInterface::id()} and remain independent from the array keys used in
     * configuration.
     *
     * @var array<array-key, array<string, mixed>|CollectorInterface|string>
     */
    public array $collectors = [];
    /**
     * Directory storing the debugger data files (path alias accepted).
     */
    public string $dataPath = '@runtime/debug';
    /**
     * Debug bar default height, as a percentage of the total screen height.
     */
    public int $defaultHeight = 50;
    /**
     * Name of the panel that should be visible when opening the debug panel.
     */
    public string $defaultPanel = 'log';
    /**
     * Route dispatched when the module is requested without an action segment.
     */
    public $defaultRoute = 'index';
    /**
     * Permission applied to newly created debugger directories (used by {@see chmod()}); no umask is applied.
     */
    public int $dirMode = 0o700;
    /**
     * Whether to disable the access-callback restriction warning emitted by {@see checkAccess()}.
     */
    public bool $disableCallbackRestrictionWarning = false;
    /**
     * Whether to disable the IP restriction warning emitted by {@see checkAccess()}.
     */
    public bool $disableIpRestrictionWarning = false;
    /**
     * Whether to keep log messages emitted by debug-module requests. Enable only when debugging the module itself.
     */
    public bool $enableDebugLogs = false;
    /**
     * Permission applied to newly created debugger data files (used by {@see chmod()}).
     */
    public int|null $fileMode = 0o600;
    /**
     * Maximum number of debug data files to keep; older snapshots beyond this count are pruned.
     */
    public int $historySize = 50;
    /**
     * LogTarget instance, configuration array, or class name. Always coerced to a {@see LogTarget} by {@see bootstrap()}.
     *
     * @var array<string, mixed>|LogTarget|string
     */
    public LogTarget|array|string $logTarget = 'yii\debug\LogTarget';
    /**
     * Maximum raw request or response body bytes retained by the shared capture policy.
     */
    public int $maxBodyBytes = 65536;
    /**
     * Page title literal string or a callable receiving the base URL and returning a string.
     *
     * @var (callable(string): string)|string|null
     */
    public mixed $pageTitle = null;
    /**
     * Debug panels indexed by panel id. May be populated with config arrays / class names before {@see initPanels()}
     * runs, but after initialization the array holds only {@see Panel} instances.
     *
     * @var array<string, Panel>
     */
    public array $panels = [];
    /**
     * @var list<string>|null PCRE patterns applied to complete data keys. `null` enables Debug Core defaults when the
     * default exact-key list is active; `[]` explicitly disables pattern matching.
     */
    public array|null $sensitiveKeyPatterns = null;
    /**
     * @var list<string> Literal, case-insensitive data-key prefixes redacted from every persistent debugger capture.
     */
    public array $sensitiveKeyPrefixes = [];
    /**
     * @var list<string> Exact, case-insensitive data keys redacted from every persistent debugger capture.
     */
    public array $sensitiveKeys = SensitiveDataRedactor::DEFAULT_KEYS;
    /**
     * Routes whose AJAX hits should NOT appear in the toolbar history (e.g. polling endpoints).
     *
     * @var array<int|string, mixed>
     */
    public array $skipAjaxRequestUrl = [];
    /**
     * Toolbar position on the page (`'bottom'` or `'upper'`).
     */
    public string $toolbarPosition = 'bottom';
    /**
     * Trace-line template placeholder string ({file}, {line}, {text}), callable returning the rendered line, or `false`
     * to disable trace-line rendering entirely.
     *
     * @var (callable(array<string, mixed>, Panel): string)|false|string
     */
    public mixed $traceLine = self::DEFAULT_IDE_TRACELINE;
    /**
     * Maps containerized/remote paths to local paths for the {file} portion of {@see $traceLine}; only the first match
     * is applied.
     *
     * @var array<string, string>
     */
    public array $tracePathMappings = [];
    /**
     * Class name of the {@see UrlRule} used for rules generated by this module.
     */
    public string $urlRuleClass = 'yii\web\UrlRule';
    /**
     * Path containing the framework-neutral Debug Core templates.
     */
    public string $viewPath = '@vendor/php-forge/debug-core/resources/views';

    private CollectorCoordinator|null $collectorCoordinator = null;

    /**
     * Cached `data:image/svg+xml;base64` URI of the Yii logo, populated lazily by {@see getYiiLogo()}.
     */
    private static string|null $yiiLogo = null;

    /**
     * Disables the application log targets when {@see $enableDebugLogs} is `false`, applies the access check, and
     * detaches the toolbar/header listeners so the debugger response is not polluted with self-debug data.
     *
     * @throws InvalidConfigException When the log component cannot be resolved.
     * @throws ForbiddenHttpException When the caller fails the access check on a non-toolbar route.
     * @throws Throwable When an active collector cannot shut down cleanly.
     */
    #[Override]
    public function beforeAction($action): bool
    {
        if (!$this->enableDebugLogs) {
            $log = $this->get('log');

            if ($log instanceof Dispatcher) {
                foreach ($log->targets as $target) {
                    // Entries stay raw configuration arrays until Dispatcher::init() resolves them.
                    if ($target instanceof Target) {
                        $target->enabled = false;
                    }
                }
            }

            $this->getCollectorCoordinator()->shutdown();
        }

        if (!parent::beforeAction($action)) {
            return false;
        }

        Yii::$app->getView()->off(View::EVENT_END_BODY, [$this, 'renderToolbar']);
        Yii::$app->getResponse()->off(Response::EVENT_AFTER_PREPARE, [$this, 'setDebugHeaders']);

        $this->setDebuggerResponseHeaders(Yii::$app->getResponse());

        if ($this->checkAccess($action)) {
            $this->resetGlobalSettings();

            return true;
        }

        if ($action->id === 'toolbar-data') {
            // Accessing the toolbar data remotely is normal do not throw.
            return false;
        }

        throw new ForbiddenHttpException(
            Message::ACCESS_DENIED->getMessage(),
        );
    }

    /**
     * Wires the debug log target, the toolbar/header listeners, the error-page injection hook, and the debugger URL
     * rules onto the application.
     *
     * Called by Yii during the application bootstrap phase (when this module is listed in `bootstrap`).
     */
    public function bootstrap($app): void
    {
        $this->logTarget = $this->resolveLogTarget();

        $app->getLog()->targets['debug'] = $this->logTarget;

        $app->on(
            Application::EVENT_BEFORE_REQUEST,
            $this->logTarget->beginRequest(...),
        );
        $app->on(
            Application::EVENT_BEFORE_REQUEST,
            $this->getCollectorCoordinator()->startup(...),
        );
        $app->on(
            Application::EVENT_BEFORE_REQUEST,
            function () use ($app): void {
                $app->getResponse()->on(Response::EVENT_AFTER_PREPARE, [$this, 'setDebugHeaders']);
            },
        );
        $app->on(
            Application::EVENT_BEFORE_ACTION,
            function (ActionEvent $event) use ($app): void {
                if ($event->action->controller === null && $this->isDebuggerAction($event->action)) {
                    $moduleAllowsAction = $this->beforeAction($event->action);
                    $event->isValid = $event->isValid && $moduleAllowsAction;

                    return;
                }

                $app->getView()->on(View::EVENT_END_BODY, [$this, 'renderToolbar']);
            },
        );

        $errorHandler = $app->errorHandler;

        $errorHandler->on(ErrorHandler::EVENT_AFTER_RENDER, [$this, 'injectToolbarOnErrorPage']);

        $id = $this->getUniqueId();

        $app->getUrlManager()->addRules(
            [
                [
                    'class' => $this->urlRuleClass,
                    'route' => $id,
                    'pattern' => $id,
                    'normalizer' => false,
                    'suffix' => false,
                ],
                [
                    'class' => $this->urlRuleClass,
                    'route' => "{$id}/<action>",
                    'pattern' => "{$id}/<action:[\w\-]+>",
                    'normalizer' => false,
                    'suffix' => false,
                ],
            ],
            false,
        );
    }

    /**
     * Creates the shared persistent-data policy, optionally extending its exact-key list for one collector.
     *
     * @param list<string> $additionalSensitiveKeys Collector-specific exact keys added without weakening global rules.
     */
    public function createCapturePolicy(array $additionalSensitiveKeys = []): CapturePolicy
    {
        $patterns = $this->sensitiveKeyPatterns;

        if (
            $patterns === null
            && $additionalSensitiveKeys !== []
            && $this->sensitiveKeys === SensitiveDataRedactor::DEFAULT_KEYS
        ) {
            $patterns = SensitiveDataRedactor::DEFAULT_PATTERNS;
        }

        return new CapturePolicy(
            sensitiveKeys: array_values(array_unique([...$this->sensitiveKeys, ...$additionalSensitiveKeys])),
            maxBodyBytes: $this->maxBodyBytes,
            sensitiveKeyPrefixes: $this->sensitiveKeyPrefixes,
            sensitiveKeyPatterns: $patterns,
        );
    }

    /**
     * Returns the validated collector coordinator used by the request log target.
     *
     * @throws InvalidConfigException When module initialization has not completed.
     *
     * @return CollectorCoordinator Configured collector coordinator.
     */
    public function getCollectorCoordinator(): CollectorCoordinator
    {
        return $this->collectorCoordinator ?? throw new InvalidConfigException(
            Message::COLLECTORS_NOT_INITIALIZED->getMessage(),
        );
    }

    /**
     * Returns the toolbar HTML: a `<yii-debug-toolbar>` custom element wired with data attributes the bundled JS
     * reads.
     */
    public function getToolbarHtml(): string
    {
        $logTarget = $this->logTargetOrFail();

        $url = Url::toRoute(
            [
                '/' . $this->getUniqueId() . '/toolbar-data',
                'tag' => $logTarget->tag,
            ],
        );

        $skipAjaxRequestUrl = [];

        foreach ($this->skipAjaxRequestUrl as $route) {
            if (is_string($route) || is_array($route)) {
                $skipAjaxRequestUrl[] = Url::to($route);
            }
        }

        return $this->toolbarRenderer()->renderElement(
            dataUrl: $url,
            skipUrls: $skipAjaxRequestUrl,
            position: $this->toolbarPosition,
            height: $this->defaultHeight,
        );
    }

    /**
     * Returns the Yii logo as a data URI ready to drop into `<img src="…">` or `<link rel="icon">`.
     *
     * Uses the shared frontend file so every framework adapter renders the same Yii mark.
     */
    public static function getYiiLogo(): string
    {
        if (self::$yiiLogo === null) {
            $svg = Icon::render('yii');

            if ($svg === '') {
                throw new RuntimeException(
                    Message::YII_LOGO_UNREADABLE->getMessage(),
                );
            }

            self::$yiiLogo = 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        return self::$yiiLogo;
    }

    /**
     * Resolves the page title used in the debugger HTML: the literal {@see $pageTitle} string when set, the result of
     * the configured callable, or the default `Yii Debugger` label.
     */
    public function htmlTitle(): string
    {
        if (is_string($this->pageTitle) && $this->pageTitle !== '') {
            return $this->pageTitle;
        }

        if (is_callable($this->pageTitle)) {
            return ($this->pageTitle)(Url::base(true));
        }

        return 'Yii Debugger';
    }

    /**
     * Resolves the {@see $dataPath} alias and instantiates every configured panel.
     *
     * @throws InvalidConfigException When a panel configuration cannot be resolved into a {@see Panel} instance.
     */
    #[Override]
    public function init(): void
    {
        parent::init();

        try {
            $this->createCapturePolicy();
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }

        Yii::setAlias(self::VIEW_PATH_ALIAS, $this->viewPath);

        $this->dataPath = Yii::getAlias($this->dataPath);

        $this->initCollectors();
        $this->initPanels();
        $this->initPanelServices();
        $this->initActionMap();
    }

    /**
     * Injects the debug toolbar into the rendered HTML of an error page (yiisoft/yii2#7616).
     *
     * Wired in {@see bootstrap()} as a listener for {@see ErrorHandler::EVENT_AFTER_RENDER}; the event fires after
     * `renderException()` produces the HTML body but before the response is sent, so handlers may rewrite the output.
     */
    public function injectToolbarOnErrorPage(ErrorHandlerRenderEvent $event): void
    {
        if (
            $this->isDebuggerAction(Yii::$app->requestedAction)
            || !$this->checkAccess()
            || Yii::$app->getRequest()->getIsAjax()
        ) {
            return;
        }

        $renderer = $this->toolbarRenderer();
        $injection = $this->getToolbarHtml() . $renderer->scriptTag();

        $event->output = $renderer->inject($event->output, $injection);
    }

    /**
     * Renders the mini-toolbar at the end of the page body.
     *
     * Wired in {@see bootstrap()} as a listener for {@see View::EVENT_END_BODY}. The toolbar template is rendered
     * dynamically while its runtime URL is resolved through the Yii2 asset manager.
     *
     * @throws Throwable When the view dynamic render fails for the current request.
     */
    public function renderToolbar(Event $event): void
    {
        if (
            $this->isDebuggerAction(Yii::$app->requestedAction)
            || !$this->checkAccess()
            || Yii::$app->getRequest()->getIsAjax()
        ) {
            return;
        }

        $view = $event->sender;

        if (!$view instanceof View) {
            return;
        }

        echo $view->renderDynamic('return Yii::$app->getModule("' . $this->getUniqueId() . '")->getToolbarHtml();');

        echo $this->toolbarRenderer($view)->scriptTag();
    }

    /**
     * Builds a module-absolute route array for the given debugger action.
     *
     * Resolves the module ID from the standalone action currently being dispatched, so widgets and views can build
     * links without a controller context; outside a debugger dispatch the conventional `debug` module ID is used.
     *
     * @param string $action Debugger action ID.
     * @param array<string, TValue> $params Query parameters merged into the route array.
     *
     * @return non-empty-array<int|string, string|TValue> Route array ready for {@see Url::to()}.
     *
     * @template TValue of int|string
     */
    public static function route(string $action, array $params = []): array
    {
        $module = Yii::$app->requestedAction?->getModule();

        $moduleId = $module instanceof self ? $module->getUniqueId() : 'debug';

        return ["/{$moduleId}/{$action}", ...$params];
    }

    /**
     * Sets headers carrying debug data on AJAX responses so the toolbar can resolve the captured tag and link back to
     * the full view.
     */
    public function setDebugHeaders(Event $event): void
    {
        if ($this->isDebuggerAction(Yii::$app->requestedAction) || !$this->checkAccess()) {
            return;
        }

        $logTarget = $this->logTargetOrFail();

        $route = $this->getUniqueId();

        $url = Url::toRoute(
            [
                "/{$route}/view",
                'tag' => $logTarget->tag,
            ],
        );

        $sender = $event->sender;

        if (!$sender instanceof Response) {
            return;
        }

        $rawStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $requestStart = Coerce::floatOrNull($rawStart) ?? microtime(true);

        $sender->getHeaders()
            ->set('X-Debug-Tag', $logTarget->tag)
            ->set('X-Debug-Duration', number_format((microtime(true) - $requestStart) * 1000, 3, '.', ''))
            ->set('X-Debug-Link', $url);
    }

    /**
     * Sets the logo data URI returned by {@see getYiiLogo()}.
     */
    public static function setYiiLogo(string $logo): void
    {
        self::$yiiLogo = $logo;
    }

    /**
     * Returns whether the current request is allowed to access the debugger.
     *
     * Checks {@see $allowedIPs}, {@see $allowedHosts}, and the optional {@see $checkAccessCallback} in that order. Warns
     * via {@see Yii::warning()} on a denial unless the matching `disable*RestrictionWarning` flag is set.
     */
    protected function checkAccess(Action|null $action = null): bool
    {
        $ip = Yii::$app->getRequest()->getUserIP() ?? '';

        $allowed = (new IpAllowlist($this->allowedIPs, $this->allowedHosts))->matches($ip);

        if ($allowed === false) {
            if (!$this->disableIpRestrictionWarning) {
                Yii::warning(
                    "Access to debugger is denied due to IP address restriction. The requesting IP address is {$ip}",
                    __METHOD__,
                );
            }

            return false;
        }

        if ($this->checkAccessCallback !== null && ($this->checkAccessCallback)($action) !== true) {
            if (!$this->disableCallbackRestrictionWarning) {
                Yii::warning(
                    'Access to debugger is denied due to checkAccessCallback.',
                    __METHOD__,
                );
            }

            return false;
        }

        return true;
    }

    /**
     * Built-in standalone actions dispatched through {@see \yii\base\Module::$actionMap}, keyed by action ID.
     *
     * @return array<string, class-string> Action classes indexed by action id.
     */
    protected function coreActionMap(): array
    {
        return [
            'compare' => CompareAction::class,
            'download-mail' => DownloadMailAction::class,
            'index' => IndexAction::class,
            'php-info' => PhpInfoAction::class,
            'reset-identity' => ResetIdentityAction::class,
            'set-identity' => SetIdentityAction::class,
            'toolbar-data' => ToolbarDataAction::class,
            'view' => ViewAction::class,
        ];
    }

    /**
     * Built-in collectors paired by stable ID with their presentation panels.
     *
     * Array keys are a configuration-merge convenience and must match each collector's {@see CollectorInterface::id()}
     * so a user entry under the same key replaces the built-in collector.
     *
     * @return array<string, class-string<CollectorInterface>> Collector classes indexed by collector id.
     */
    protected function coreCollectors(): array
    {
        return [
            'asset' => AssetCollector::class,
            'config' => ConfigCollector::class,
            'db' => DbCollector::class,
            'dump' => DumpCollector::class,
            'event' => EventCollector::class,
            'inertia' => InertiaCollector::class,
            'vite' => ViteCollector::class,
            'log' => LogCollector::class,
            'mail' => MailCollector::class,
            'profiling' => ProfilingCollector::class,
            'queue' => QueueCollector::class,
            'request' => RequestCollector::class,
            'router' => RouterCollector::class,
            'user' => UserCollector::class,
        ];
    }

    /**
     * Returns the built-in panel configurations, ordered as the request itself unfolds.
     *
     * The primary navigation starts with Request, Logs, Events, and Profiling before the remaining Yii diagnostics.
     * Optional integration panels finish the list in the order Inertia, Mail, Queue, and Vite. `config` opens the list
     * but is surfaced through the brand bar rather than the panel nav.
     *
     * @return array<string, array<string, mixed>|class-string<Panel>> Panel definitions indexed by panel id.
     */
    protected function corePanels(): array
    {
        return [
            'config' => ConfigPanel::class,
            'request' => RequestPanel::class,
            'log' => LogPanel::class,
            'event' => EventPanel::class,
            'profiling' => ProfilingPanel::class,
            'router' => ['class' => RouterPanel::class, 'standalone' => false],
            'user' => UserPanel::class,
            'db' => DbPanel::class,
            'dump' => DumpPanel::class,
            'asset' => AssetPanel::class,
            'inertia' => InertiaPanel::class,
            'mail' => MailPanel::class,
            'queue' => QueuePanel::class,
            'vite' => VitePanel::class,
        ];
    }

    /**
     * Resolves a debugger endpoint from {@see \yii\base\Module::$actionMap} before falling back to convention-based
     * discovery.
     *
     * Application-level dispatch of a module-prefixed route (for example `debug/db-explain`) reaches this method
     * through {@see \yii\base\Module::createStandaloneAction()}. Convention discovery derives a root-namespace class
     * from the action ID (`db-explain` becomes `DbExplainAction`) and cannot see the sub-namespaced panel action
     * classes ({@see \yii\debug\actions\db\ExplainAction}, {@see \yii\debug\actions\queue\JobAction}), so the mapped
     * entries are consulted first to keep those routes reachable.
     */
    #[Override]
    protected function createStandaloneAction(string $route): Action|null
    {
        if ($route === '') {
            $route = $this->defaultRoute;
        }

        $id = trim($route, '/');

        if ($id !== '' && !str_contains($id, '/') && isset($this->actionMap[$id])) {
            $action = ComponentResolver::createMapped($this->actionMap[$id]);

            if ($action instanceof Action) {
                $action->id = $id;

                $action->setModule($this);

                return $action;
            }
        }

        return parent::createStandaloneAction($route);
    }

    /**
     * Returns the default module version string.
     */
    #[Override]
    protected function defaultVersion(): string
    {
        return VersionResolver::forPackage('yii2-extensions/debug') ?? 'unknown';
    }

    /**
     * Merges the built-in and panel-declared standalone actions into {@see \yii\base\Module::$actionMap}.
     *
     * Precedence, lowest to highest: built-in actions from {@see coreActionMap()}, actions declared by registered
     * panels through {@see Panel::$actions}, and entries configured directly on `actionMap`.
     */
    protected function initActionMap(): void
    {
        $panelActions = [];

        foreach ($this->panels as $panel) {
            foreach ($panel->actions as $id => $action) {
                $panelActions[$id] = $action;
            }
        }

        $this->actionMap = [...$this->coreActionMap(), ...$panelActions, ...$this->actionMap];
    }

    /**
     * Resolves configured collectors and validates their stable IDs before request capture.
     *
     * Built-in extension collectors are omitted when their provider package is unavailable. Explicit application
     * configuration remains authoritative and may still register a custom collector under the same ID.
     *
     * @throws InvalidConfigException When a collector configuration or ID is invalid.
     */
    protected function initCollectors(): void
    {
        $coreCollectors = $this->availableCoreDefinitions($this->coreCollectors());
        $merged = [...array_diff_key($coreCollectors, $this->collectors), ...$this->collectors];
        $collectors = [];

        foreach ($merged as $config) {
            $collector = $this->buildCollector($config);

            if ($collector instanceof Collector) {
                $collector->module = $this;
            }

            $collectors[] = $collector;
        }

        $this->collectors = $collectors;

        try {
            $this->collectorCoordinator = new CollectorCoordinator($collectors);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /**
     * Merges custom panels on top of the available built-in panels and instantiates each entry, dropping any panel
     * whose {@see Panel::isEnabled()} returns `false`. Explicit application configuration remains authoritative when
     * an optional provider package is unavailable.
     *
     * @throws InvalidConfigException When a panel configuration cannot be resolved into a {@see Panel} instance.
     */
    protected function initPanels(): void
    {
        $corePanels = $this->availableCoreDefinitions($this->corePanels());
        $merged = [...array_diff_key($corePanels, $this->panels), ...$this->panels];

        $this->panels = [];

        foreach ($merged as $id => $config) {
            $panel = $this->buildPanel($id, $config);

            if ($panel !== null && $panel->isEnabled()) {
                $this->panels[$id] = $panel;
            }
        }
    }

    /**
     * Registers every enabled panel in the module service locator under its own class and each ancestor class below
     * {@see Panel}.
     *
     * Standalone actions receive the registered instance through a typed `run()` parameter resolved by the
     * standalone-action binder, so a configured panel subclass satisfies a built-in type hint such as
     * `DbPanel $panel`. The generic {@see Panel} base class is never registered because multiple panels would compete
     * for it; when panels share a class chain, the later entry in {@see $panels} order wins, matching the action ID
     * precedence in {@see initActionMap()}.
     */
    protected function initPanelServices(): void
    {
        foreach ($this->panels as $panel) {
            $class = $panel::class;

            while ($class !== false && $class !== Panel::class) {
                $this->set($class, $panel);

                $class = get_parent_class($class);
            }
        }
    }

    /**
     * Resets application-wide settings the debugger should not inherit from the host application (currently the
     * asset bundles registry).
     */
    protected function resetGlobalSettings(): void
    {
        Yii::$app->assetManager->bundles = [];
    }

    /**
     * Applies the debugger response hardening policy.
     *
     * Kept as an extension point so applications can customize the policy without replacing the complete
     * {@see beforeAction()} lifecycle.
     */
    protected function setDebuggerResponseHeaders(Response $response): void
    {
        DebugResponseHeaders::apply($response);
    }

    /**
     * Removes unavailable optional integrations from a built-in definition map.
     *
     * @template TDefinition
     *
     * @param array<string, TDefinition> $definitions Built-in collectors or panels indexed by stable ID.
     *
     * @return array<string, TDefinition> Definitions whose runtime providers are installed.
     */
    private function availableCoreDefinitions(array $definitions): array
    {
        foreach ($definitions as $id => $_definition) {
            if (ExtensionAvailability::isAvailable($id) === false) {
                unset($definitions[$id]);
            }
        }

        return $definitions;
    }

    /**
     * Resolves a collector instance, class name, or Yii configuration array.
     *
     * @param array<string, mixed>|CollectorInterface|string $config Collector configuration.
     *
     * @throws InvalidConfigException When the configuration does not resolve to a collector.
     *
     * @return CollectorInterface Resolved collector.
     */
    private function buildCollector(CollectorInterface|array|string $config): CollectorInterface
    {
        if ($config instanceof CollectorInterface) {
            return $config;
        }

        [$class, $properties] = ComponentResolver::classAndProperties($config);

        if ($class === null) {
            throw new InvalidConfigException(
                Message::COLLECTOR_CLASS_INVALID->getMessage(),
            );
        }

        $collector = Yii::$container->get($class, [], $properties);

        if (!$collector instanceof CollectorInterface) {
            throw new InvalidConfigException(
                Message::COLLECTOR_INTERFACE_INVALID->getMessage(CollectorInterface::class, $class),
            );
        }

        return $collector;
    }

    /**
     * Resolves a panel configuration into a {@see Panel} instance, binding `id` and `module` references and firing
     * {@see Panel::moduleBound()} once both references are in place.
     *
     * @param array<string, mixed>|Panel|string $config Panel instance, configuration array, or class-name string.
     *
     * @throws InvalidConfigException When the container fails to build the panel.
     *
     * @return Panel|null Resolved panel, or `null` when the class name is unknown.
     */
    private function buildPanel(string $id, Panel|array|string $config): Panel|null
    {
        if ($config instanceof Panel) {
            $config->id = $id;
            $config->module = $this;

            $config->moduleBound();

            return $config;
        }

        if (is_string($config)) {
            $class = $config;
            $properties = [];
        } else {
            [$class, $properties] = ComponentResolver::classAndProperties($config);

            if ($class === null) {
                return null;
            }
        }

        $properties['module'] = $this;
        $properties['id'] = $id;

        $object = Yii::$container->get($class, [], $properties);

        if (!$object instanceof Panel) {
            return null;
        }

        $object->moduleBound();

        return $object;
    }

    /**
     * Returns whether the requested action belongs to this debugger module.
     */
    private function isDebuggerAction(Action|null $action): bool
    {
        $module = $action?->getModule();

        while ($module !== null) {
            if ($module === $this) {
                return true;
            }

            $module = $module->module;
        }

        return false;
    }

    /**
     * Returns the initialized {@see LogTarget}, raising when the module has not been bootstrapped.
     *
     * @throws InvalidConfigException When {@see bootstrap()} has not run yet (so {@see $logTarget} is still a config
     * array or class name).
     */
    private function logTargetOrFail(): LogTarget
    {
        if (!$this->logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_NOT_BOOTSTRAPPED->getMessage(),
            );
        }

        return $this->logTarget;
    }

    /**
     * Resolves the {@see $logTarget} configuration into a {@see LogTarget} instance, accepting a class-name string,
     * a configuration array with a `class` key, or an already-instantiated target.
     *
     * @throws InvalidConfigException When the configured class is missing or does not produce a {@see LogTarget}.
     */
    private function resolveLogTarget(): LogTarget
    {
        if ($this->logTarget instanceof LogTarget) {
            return $this->logTarget;
        }

        [$class, $properties] = ComponentResolver::classAndProperties($this->logTarget, LogTarget::class);

        if ($class === null) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_CLASS_INVALID->getMessage(),
            );
        }

        $target = Yii::$container->get($class, [$this], $properties);

        if (!$target instanceof LogTarget) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_INSTANCE_INVALID->getMessage(),
            );
        }

        return $target;
    }

    /**
     * Creates the Yii2-specific toolbar renderer.
     *
     * @param BaseView|null $view View handling the current response or `null` to use the application view.
     *
     * @return ToolbarRenderer Configured toolbar renderer.
     */
    private function toolbarRenderer(BaseView|null $view = null): ToolbarRenderer
    {
        $view ??= Yii::$app->getView();

        return new ToolbarRenderer(
            $view,
            Yii::$app->getAssetManager(),
            self::VIEW_PATH_ALIAS,
        );
    }
}
