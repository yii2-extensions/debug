<?php

declare(strict_types=1);

namespace yii\debug;

use InvalidArgumentException;
use Override;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\{CollectorInterface, Panel as PortablePanel};
use PHPForge\Debug\Helper\{Coerce, Icon, SensitiveDataRedactor, Trace};
use PHPForge\Debug\Registration\{PanelOverride, PanelRegistration, PanelRegistry};
use PHPForge\Debug\Toolbar\DebugHeader;
use PHPForge\Debug\View\ViewMessage;
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
    LogCollector,
    MailCollector,
    ProfilingCollector,
    QueueCollector,
    RequestCollector,
    RouterCollector,
    UserCollector,
};
use yii\debug\exception\Message;
use yii\debug\panels\{
    AssetPanel,
    ConfigPanel,
    DbPanel,
    DumpPanel,
    EventPanel,
    LogPanel,
    MailPanel,
    ProfilingPanel,
    ProviderPanel,
    QueuePanel,
    RequestPanel,
    RouterPanel,
    UserPanel,
};
use yii\helpers\Url;
use yii\log\{Dispatcher, Target};
use yii\rbac\BaseManager;
use yii\web\{ErrorHandler, ErrorHandlerRenderEvent, ForbiddenHttpException, Response, View};

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function base64_encode;
use function get_parent_class;
use function is_array;
use function is_bool;
use function is_callable;
use function is_string;
use function is_subclass_of;
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
     * Default {@see $traceLine} template: the shared `ide://` deep link that IDE extensions resolve into "open file at
     * line".
     */
    public const string DEFAULT_IDE_TRACELINE = Trace::DEFAULT_TEMPLATE;
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
     * Collectors derive their own IDs; register them as a list or under a key that matches {@see
     * CollectorInterface::id()}.
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
    public int $maxBodyBytes = CapturePolicy::DEFAULT_MAX_BODY_BYTES;
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

    /**
     * Coordinator responsible for managing data collectors.
     */
    private CollectorCoordinator|null $collectorCoordinator = null;

    /**
     * Effective panel catalog resolved from the provider defaults and the configured registration options.
     */
    private PanelRegistry|null $panelRegistry = null;

    /**
     * Cached `data:image/svg+xml;base64` URI of the Yii logo, populated lazily by {@see getYiiLogo()}.
     */
    private static string|null $yiiLogo = null;

    /**
     * Disables the application log targets when {@see $enableDebugLogs} is `false`, applies the access check, and
     * detaches the toolbar/header listeners so the debugger response is not polluted with self-debug data.
     *
     * @param Action $action Action about to run.
     *
     * @throws InvalidConfigException when the log component cannot be resolved.
     * @throws ForbiddenHttpException when the caller fails the access check on a non-toolbar route.
     * @throws Throwable when an active collector cannot shut down cleanly.
     *
     * @return bool `true` when the action may run; `false` to stop the dispatch.
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
     *
     * @param Application $app Application being bootstrapped.
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

        $errorHandler->on(
            ErrorHandler::EVENT_AFTER_RENDER,
            [$this, 'injectToolbarOnErrorPage'],
        );

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
     *
     * @return CapturePolicy Shared policy covering the global rules and the collector-specific keys.
     */
    public function createCapturePolicy(array $additionalSensitiveKeys = []): CapturePolicy
    {
        $capturePolicy = new CapturePolicy(
            sensitiveKeys: $this->sensitiveKeys,
            maxBodyBytes: $this->maxBodyBytes,
            sensitiveKeyPrefixes: $this->sensitiveKeyPrefixes,
            sensitiveKeyPatterns: $this->sensitiveKeyPatterns,
        );

        return $capturePolicy->withAdditionalSensitiveKeys($additionalSensitiveKeys);
    }

    /**
     * Returns the validated collector coordinator used by the request log target.
     *
     * @throws InvalidConfigException when module initialization has not completed.
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
     * Returns the effective panel catalog, which carries the display order and the IDs disabled by configuration.
     *
     * @throws InvalidConfigException when module initialization has not completed.
     *
     * @return PanelRegistry Resolved panel catalog.
     */
    public function getPanelRegistry(): PanelRegistry
    {
        return $this->panelRegistry ?? throw new InvalidConfigException(
            Message::PANELS_NOT_INITIALIZED->getMessage(),
        );
    }

    /**
     * Returns the toolbar HTML: a `<yii-debug-toolbar>` custom element wired with data attributes the bundled JS
     * reads.
     *
     * @return string Rendered `<yii-debug-toolbar>` element.
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
     *
     * @return string Logo as a data URI.
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
     *
     * @return string Page title for the debugger HTML.
     */
    public function htmlTitle(): string
    {
        if (is_string($this->pageTitle) && $this->pageTitle !== '') {
            return $this->pageTitle;
        }

        if (is_callable($this->pageTitle)) {
            return ($this->pageTitle)(Url::base(true));
        }

        return ViewMessage::TITLE->value;
    }

    /**
     * Resolves the {@see $dataPath} alias and instantiates every configured panel.
     *
     * @throws InvalidConfigException when a panel configuration cannot be resolved into a {@see Panel} instance.
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
     *
     * @param ErrorHandlerRenderEvent $event Render event carrying the error-page HTML.
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
     * @param Event $event End-of-body event raised by the view.
     *
     * @throws Throwable when the view dynamic render fails for the current request.
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

        return [
            "/{$moduleId}/{$action}",
            ...$params,
        ];
    }

    /**
     * Sets headers carrying debug data on AJAX responses so the toolbar can resolve the captured tag and link back to
     * the full view.
     *
     * @param Event $event Response event raised after the action ran.
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
            ->set(DebugHeader::TAG->value, $logTarget->tag)
            ->set(
                DebugHeader::DURATION->value,
                number_format((microtime(true) - $requestStart) * 1000, 3, '.', ''),
            )
            ->set(DebugHeader::LINK->value, $url);
    }

    /**
     * Sets the logo data URI returned by {@see getYiiLogo()}.
     *
     * @param string $logo Logo as a data URI.
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
     *
     * @param Action|null $action Action being dispatched, or `null` outside an action context.
     *
     * @return bool `true` when the request may reach the debugger; `false` otherwise.
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
                    Message::ACCESS_DENIED_BY_CALLBACK->value,
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
     * The primary navigation starts with Request, Logs, Events, Profiling, and Database before the remaining Yii diagnostics.
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
            'db' => DbPanel::class,
            'router' => ['class' => RouterPanel::class, 'standalone' => false],
            'user' => UserPanel::class,
            'dump' => DumpPanel::class,
            'asset' => AssetPanel::class,
            'mail' => MailPanel::class,
            'queue' => QueuePanel::class,
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
     *
     * @param string $route Action route relative to this module.
     *
     * @return Action|null Resolved action, or `null` when the route matches none.
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
     *
     * @return string Version label reported for this module.
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
     * configuration remains authoritative and may still register a custom collector under the same ID. An array entry
     * declaring `enabled` as `false` is skipped before its class is resolved, so an uninstalled optional package is
     * not an error.
     *
     * Each resolved collector is instrumented right away through {@see Collector::instrument()}: {@see initPanels()}
     * runs afterwards and a panel constructor may already hit the framework, so instrumentation installed only at
     * {@see Application::EVENT_BEFORE_REQUEST} would miss the debugger's own bootstrap work.
     *
     * @throws InvalidConfigException When a collector configuration or ID is invalid.
     */
    protected function initCollectors(): void
    {
        $coreCollectors = $this->availableCoreDefinitions($this->coreCollectors());

        $merged = [...array_diff_key($coreCollectors, $this->collectors), ...$this->collectors];
        $collectors = [];

        foreach ($merged as $id => $config) {
            if (is_array($config) && array_key_exists('enabled', $config)) {
                $enabled = $config['enabled'];

                unset($config['enabled']);

                if (is_bool($enabled) === false) {
                    throw new InvalidConfigException(
                        Message::COLLECTOR_ENABLED_INVALID->getMessage((string) $id),
                    );
                }

                if ($enabled === false) {
                    continue;
                }
            }

            $collector = $this->buildCollector($config);

            if (is_string($id) && $id !== $collector->id()) {
                throw new InvalidConfigException(
                    Message::PROVIDER_ID_MISMATCH->getMessage('collector'),
                );
            }

            if ($collector instanceof Collector) {
                $collector->module = $this;

                $collector->instrument();
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
     * @throws InvalidConfigException when a panel configuration, a registration option, or the resolved catalog is
     * invalid.
     */
    protected function initPanels(): void
    {
        $corePanels = $this->availableCoreDefinitions($this->corePanels());

        $merged = [...array_diff_key($corePanels, $this->panels), ...$this->panels];

        $this->resolvePanels($merged);
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
     *
     * @param Response $response Response about to be sent.
     */
    protected function setDebuggerResponseHeaders(Response $response): void
    {
        DebugResponseHeaders::apply($response);
    }

    /**
     * Wraps a provider-owned declarative panel in the host adapter under the provider's own ID.
     *
     * @param int|string $key Registration key of the panel; a string key must match the provider's own ID.
     * @param PortablePanel $provider Declarative panel to adapt.
     *
     * @throws InvalidConfigException when the registration key contradicts the provider ID.
     *
     * @return ProviderPanel Adapter carrying the provider.
     */
    private function adaptProvider(int|string $key, PortablePanel $provider): ProviderPanel
    {
        if (is_string($key) && $key !== $provider->id()) {
            throw new InvalidConfigException(
                Message::PROVIDER_ID_MISMATCH->getMessage('panel'),
            );
        }

        return new ProviderPanel(['id' => $provider->id(), 'provider' => $provider]);
    }

    /**
     * Rejects a `title` or `icon` override on a panel that renders the metadata it declares itself.
     *
     * @param string $id Registration ID of the panel.
     * @param PanelOverride $override Registration options declared for that panel.
     *
     * @throws InvalidConfigException when the override declares a title or an icon.
     */
    private static function assertNoMetadataOverride(string $id, PanelOverride $override): void
    {
        if ($override->title !== null || $override->icon !== null) {
            throw new InvalidConfigException(
                Message::PANEL_METADATA_OVERRIDE_UNSUPPORTED->getMessage($id),
            );
        }
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
     * Binds a resolved panel to this module and fires {@see Panel::moduleBound()} once the references are in place.
     *
     * @param Panel $panel Panel to bind.
     *
     * @throws InvalidConfigException when the panel rejects the module binding.
     *
     * @return Panel Bound panel.
     */
    private function bindPanel(Panel $panel): Panel
    {
        $panel->module = $this;

        $panel->moduleBound();

        return $panel;
    }

    /**
     * Resolves a collector instance, class name, or Yii configuration array.
     *
     * @param array<string, mixed>|CollectorInterface|string $config Collector configuration.
     *
     * @throws InvalidConfigException when the configuration does not resolve to a collector.
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
     * Resolves a panel registration into a {@see Panel} instance, binding `id` and `module` references and firing
     * {@see Panel::moduleBound()} once both references are in place.
     *
     * A class string or a `class` entry naming a portable {@see PortablePanel} is built through the container and
     * adapted by {@see ProviderPanel}, exactly as an already-instantiated provider is.
     *
     * @param int|string $key Registration key of the panel; a string key must match the provider's own ID.
     * @param array<string, mixed>|Panel|PortablePanel|string $config Panel or provider instance, configuration array,
     * or class-name string.
     *
     * @throws InvalidConfigException when the class name is unresolvable, the registration key contradicts the
     * provider ID, or the container returns an object outside the panel contract.
     *
     * @return Panel Resolved panel bound to this module.
     */
    private function buildPanel(int|string $key, Panel|PortablePanel|array|string $config): Panel
    {
        if ($config instanceof PortablePanel) {
            return $this->bindPanel($this->adaptProvider($key, $config));
        }

        if ($config instanceof Panel) {
            $config->id = (string) $key;

            return $this->bindPanel($config);
        }

        [$class, $properties] = ComponentResolver::classAndProperties($config);

        if ($class === null) {
            throw new InvalidConfigException(
                Message::PANEL_CLASS_INVALID->getMessage((string) $key),
            );
        }

        if (is_subclass_of($class, PortablePanel::class)) {
            $provider = Yii::$container->get($class, [], $properties);

            if (!$provider instanceof PortablePanel) {
                throw new InvalidConfigException(
                    Message::PANEL_INSTANCE_INVALID->getMessage((string) $key, PortablePanel::class, $class),
                );
            }

            return $this->bindPanel($this->adaptProvider($key, $provider));
        }

        $properties['module'] = $this;
        $properties['id'] = (string) $key;

        $object = Yii::$container->get($class, [], $properties);

        if (!$object instanceof Panel) {
            throw new InvalidConfigException(
                Message::PANEL_INSTANCE_INVALID->getMessage((string) $key, Panel::class, $class),
            );
        }

        return $this->bindPanel($object);
    }

    /**
     * Returns whether the requested action belongs to this debugger module.
     *
     * @param Action|null $action Action to classify, or `null` when none is running.
     *
     * @return bool `true` when the action is dispatched by this module; `false` otherwise.
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
     * @throws InvalidConfigException when {@see bootstrap()} has not run yet (so {@see $logTarget} is still a config
     * array or class name).
     *
     * @return LogTarget Initialized log target of this module.
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
     * Reads the registration options an array definition declares, without resolving its class.
     *
     * An entry disabling itself returns immediately, so a definition naming an uninstalled optional package never
     * reaches the autoloader. A portable definition accepts nothing beyond `class` and the registration options, so
     * any other key is rejected by name; a Yii panel definition keeps its remaining entries as component properties.
     *
     * @param array<array-key, mixed> $definition Panel definition declared by the application.
     *
     * @throws InvalidConfigException when an option is unknown, or carries an unsupported value.
     *
     * @return PanelOverride Registration options declared by the definition.
     */
    private static function panelOverride(array $definition): PanelOverride
    {
        if (($definition['enabled'] ?? null) === false) {
            return new PanelOverride(enabled: false);
        }

        [$class, $properties] = ComponentResolver::classAndProperties($definition);

        $options = $class !== null && is_subclass_of($class, PortablePanel::class)
            ? $properties
            : array_intersect_key($properties, array_flip(PanelOverride::KEYS));

        try {
            return PanelOverride::fromArray($options);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /**
     * Resolves the {@see $logTarget} configuration into a {@see LogTarget} instance, accepting a class-name string,
     * a configuration array with a `class` key, or an already-instantiated target.
     *
     * @throws InvalidConfigException when the configured class is missing or does not produce a {@see LogTarget}.
     *
     * @return LogTarget Target built from the configured class name, array, or instance.
     */
    private function resolveLogTarget(): LogTarget
    {
        if ($this->logTarget instanceof LogTarget) {
            return $this->logTarget;
        }

        [$class, $properties] = ComponentResolver::classAndProperties(
            $this->logTarget,
            LogTarget::class,
        );

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
     * Resolves the effective panel catalog and reorders {@see $panels} to match it.
     *
     * Defaults are read from the registered panels in registration order, so built-ins keep the order
     * {@see corePanels()} declares and only the extensions are reordered by the shared policy. The resolved title and
     * icon reach the panels the host renders metadata for, and a panel declaring no name registers under its ID, which
     * the policy requires to be non-empty.
     *
     * @param array<string, PanelOverride> $overrides Registration options indexed by panel ID.
     *
     * @throws InvalidConfigException when the declared metadata or a registration option is rejected by the policy.
     *
     * @return PanelRegistry Resolved panel catalog.
     */
    private function resolvePanelRegistry(array $overrides): PanelRegistry
    {
        try {
            $defaults = [];

            foreach ($this->panels as $id => $panel) {
                $name = $panel->getName();

                $title = $name === '' ? $id : $name;
                $icon = $panel->getToolbarIcon() ?? '';

                $defaults[] = ExtensionAvailability::isExtensionPanel($id, $panel)
                    ? PanelRegistration::extension($id, $title, $icon)
                    : PanelRegistration::builtIn($id, $title, $icon);
            }

            $registry = PanelRegistry::resolve($defaults, $overrides);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigException(
                $exception->getMessage(),
                0,
                $exception,
            );
        }

        $ordered = [];

        foreach ($registry->enabled() as $registration) {
            // Every enabled registration carries a `$this->panels` key, so this guard is unreachable.
            // @infection-ignore-all
            $panel = $this->panels[$registration->id] ?? throw new InvalidConfigException(
                Message::DEBUG_PANEL_NOT_FOUND->getMessage($registration->id),
            );

            if ($panel instanceof ProviderPanel) {
                $panel->title = $registration->title;
                $panel->icon = $registration->icon === '' ? null : $registration->icon;
            }

            $ordered[$registration->id] = $panel;
        }

        $this->panels = $ordered;

        return $registry;
    }

    /**
     * Instantiates every configured panel, binds it to this module, and stores the catalog in display order.
     *
     * An entry declaring `enabled` as `false` is skipped before its class is resolved and is reported by
     * {@see PanelRegistry::disabled()}; `title` and `icon` overrides apply to portable panels only, because a Yii
     * panel renders the metadata it declares itself.
     *
     * @param array<array-key, array<string, mixed>|Panel|PortablePanel|string> $definitions Panel definitions to
     * resolve.
     *
     * @throws InvalidConfigException when a definition, a registration option, or the resolved catalog is invalid.
     */
    private function resolvePanels(array $definitions): void
    {
        $this->panels = [];

        $overrides = [];

        foreach ($definitions as $key => $definition) {
            $override = null;

            if (is_array($definition)) {
                $override = self::panelOverride($definition);

                if ($override->enabled === false) {
                    if (is_string($key)) {
                        $overrides[$key] = $override;
                    }

                    continue;
                }

                $definition = array_diff_key($definition, array_flip(PanelOverride::KEYS));
            }

            $panel = $this->buildPanel($key, $definition);

            if ($override !== null && !$panel instanceof ProviderPanel) {
                self::assertNoMetadataOverride($panel->id, $override);
            }

            if (isset($this->panels[$panel->id])) {
                throw new InvalidConfigException(
                    Message::PANEL_ID_DUPLICATE->getMessage($panel->id),
                );
            }

            if ($panel->isEnabled() === false) {
                continue;
            }

            $this->panels[$panel->id] = $panel;

            if ($override !== null) {
                $overrides[$panel->id] = $override;
            }
        }

        $this->panelRegistry = $this->resolvePanelRegistry($overrides);
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

        return new ToolbarRenderer($view, Yii::$app->getAssetManager(), self::VIEW_PATH_ALIAS);
    }
}
