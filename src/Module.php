<?php

declare(strict_types=1);

namespace yii\debug;

use Closure;
use InvalidArgumentException;
use Override;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\{CollectorInterface, Panel as PortablePanel};
use PHPForge\Debug\Helper\{SensitiveDataRedactor, Trace};
use PHPForge\Debug\Registration\PanelRegistry;
use PHPForge\Debug\View\ViewMessage;
use Throwable;
use Yii;
use yii\base\{Action, ActionEvent, Application, BootstrapInterface, Event, InvalidConfigException};
use yii\debug\exception\Message;
use yii\debug\service\{
    AccessGuard,
    CapturePolicyFactory,
    CollectorRegistrar,
    CoreDefinitions,
    LogTargetFactory,
    PanelRegistrar,
    ProviderCollectorAttacher,
    StandaloneActionResolver,
    ToolbarPresenter,
    YiiLogo,
};
use yii\helpers\Url;
use yii\log\{Dispatcher, Target};
use yii\rbac\BaseManager;
use yii\web\{ErrorHandler, ErrorHandlerRenderEvent, ForbiddenHttpException, Response, View};

use function array_values;
use function get_parent_class;
use function is_callable;
use function is_object;
use function is_string;

/**
 * Bootstraps the debug toolbar and the full-page debugger over the active application.
 *
 * Attaches a {@see LogTarget} to capture per-request data, registers URL rules for the debugger routes, wires the
 * toolbar/exception-page injection listeners, and registers the collectors and panels declared in {@see $collectors}
 * and {@see $panels} (merged on top of the built-in core definitions).
 *
 * Registration itself is delegated to the services under `yii\debug\service`, which the module resolves through its
 * own service locator. An application replaces one by registering a definition under the service class name: a class
 * name, a configuration array carrying `class`, a callable, or a ready-made instance. Every definition but an
 * instance is built with this module bound to the `module` argument, so a replacement declaring `Module $module`
 * receives it.
 *
 * The definition must be in place before the module resolves the service. {@see CapturePolicyFactory},
 * {@see CollectorRegistrar}, {@see PanelRegistrar}, and {@see StandaloneActionResolver} are resolved during
 * {@see init()} and {@see LogTargetFactory} during {@see bootstrap()}, so replacing those requires the module
 * `components` configuration; the remaining services may also be replaced later through
 * {@see \yii\di\ServiceLocator::set()}.
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
     *
     * @throws InvalidConfigException when the log-target configuration does not resolve to a {@see LogTarget}.
     */
    public function bootstrap($app): void
    {
        $this->logTarget = $this
            ->service(LogTargetFactory::class, fn(): LogTargetFactory => new LogTargetFactory($this))
            ->create();

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
                $this
                    ->service(
                        ProviderCollectorAttacher::class,
                        fn(): ProviderCollectorAttacher => new ProviderCollectorAttacher($this),
                    )
                    ->attach($app);
            },
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
     * @throws InvalidArgumentException when the configured keys, prefixes, patterns, or body limit are rejected.
     * @throws InvalidConfigException when the registered {@see CapturePolicyFactory} definition is invalid.
     *
     * @return CapturePolicy Shared policy covering the global rules and the collector-specific keys.
     */
    public function createCapturePolicy(array $additionalSensitiveKeys = []): CapturePolicy
    {
        return $this
            ->service(CapturePolicyFactory::class, fn(): CapturePolicyFactory => new CapturePolicyFactory($this))
            ->create($additionalSensitiveKeys);
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
     * Returns the initialized {@see LogTarget}, raising when the module has not been bootstrapped.
     *
     * @throws InvalidConfigException when {@see bootstrap()} has not run yet, so {@see $logTarget} is still a
     * configuration array or a class name.
     *
     * @return LogTarget Initialized log target of this module.
     */
    public function getLogTarget(): LogTarget
    {
        if (!$this->logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_NOT_BOOTSTRAPPED->getMessage(),
            );
        }

        return $this->logTarget;
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
     * @throws InvalidConfigException when {@see bootstrap()} has not run yet.
     *
     * @return string Rendered `<yii-debug-toolbar>` element.
     */
    public function getToolbarHtml(): string
    {
        return $this->toolbarPresenter()->html();
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
        return YiiLogo::dataUri();
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
     *
     * @throws InvalidConfigException when {@see bootstrap()} has not run yet.
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

        $this->toolbarPresenter()->injectIntoErrorPage($event);
    }

    /**
     * Renders the mini-toolbar at the end of the page body.
     *
     * Wired in {@see bootstrap()} as a listener for {@see View::EVENT_END_BODY}. The toolbar template is rendered
     * dynamically while its runtime URL is resolved through the Yii2 asset manager.
     *
     * @param Event $event End-of-body event raised by the view.
     *
     * @throws InvalidConfigException when {@see bootstrap()} has not run yet.
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

        $this->toolbarPresenter()->render($view);
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
     *
     * @throws InvalidConfigException when {@see bootstrap()} has not run yet.
     */
    public function setDebugHeaders(Event $event): void
    {
        if ($this->isDebuggerAction(Yii::$app->requestedAction) || !$this->checkAccess()) {
            return;
        }

        $sender = $event->sender;

        if (!$sender instanceof Response) {
            return;
        }

        $this->toolbarPresenter()->writeDebugHeaders($sender);
    }

    /**
     * Sets the logo data URI returned by {@see getYiiLogo()}.
     *
     * @param string $logo Logo as a data URI.
     */
    public static function setYiiLogo(string $logo): void
    {
        YiiLogo::set($logo);
    }

    /**
     * Returns whether the current request is allowed to access the debugger.
     *
     * Decided by {@see AccessGuard} from {@see $allowedIPs}, {@see $allowedHosts}, and the optional
     * {@see $checkAccessCallback}, for the IP the current request carries.
     *
     * @param Action|null $action Action being dispatched, or `null` outside an action context.
     *
     * @throws InvalidConfigException when the registered {@see AccessGuard} definition is invalid.
     *
     * @return bool `true` when the request may reach the debugger; `false` otherwise.
     */
    protected function checkAccess(Action|null $action = null): bool
    {
        return $this
            ->service(AccessGuard::class, fn(): AccessGuard => new AccessGuard($this))
            ->allows(Yii::$app->getRequest()->getUserIP() ?? '', $action);
    }

    /**
     * Built-in standalone actions dispatched through {@see \yii\base\Module::$actionMap}, keyed by action ID.
     *
     * @return array<string, class-string> Action classes indexed by action id.
     */
    protected function coreActionMap(): array
    {
        return [
            'compare' => \yii\debug\actions\CompareAction::class,
            'download-mail' => \yii\debug\actions\DownloadMailAction::class,
            'index' => \yii\debug\actions\IndexAction::class,
            'php-info' => \yii\debug\actions\PhpInfoAction::class,
            'reset-identity' => \yii\debug\actions\ResetIdentityAction::class,
            'set-identity' => \yii\debug\actions\SetIdentityAction::class,
            'toolbar-data' => \yii\debug\actions\ToolbarDataAction::class,
            'view' => \yii\debug\actions\ViewAction::class,
        ];
    }

    /**
     * Built-in collectors paired by stable ID with their presentation panels.
     *
     * Array keys are a configuration-merge convenience and must match each collector's {@see CollectorInterface::id()}
     * so a user entry under the same key replaces the built-in collector.
     *
     * A built-in entry names its collector class. {@see ProviderCatalog} contributes the collector of every optional
     * provider package the application installed, as a class name or as a configuration array carrying the arguments
     * this module's capture policy builds, so captured values follow the host redaction rules.
     *
     * @return array<string, array<string, mixed>|string> Collector definitions indexed by collector id.
     */
    protected function coreCollectors(): array
    {
        $policy = $this->createCapturePolicy();

        return [
            'asset' => \yii\debug\collectors\AssetCollector::class,
            'config' => \yii\debug\collectors\ConfigCollector::class,
            'db' => \yii\debug\collectors\DbCollector::class,
            'dump' => \yii\debug\collectors\DumpCollector::class,
            'event' => \yii\debug\collectors\EventCollector::class,
            ...ProviderCatalog::packaged()->collectors($policy),
            'log' => \yii\debug\collectors\LogCollector::class,
            'mail' => \yii\debug\collectors\MailCollector::class,
            'profiling' => \yii\debug\collectors\ProfilingCollector::class,
            'queue' => \yii\debug\collectors\QueueCollector::class,
            'request' => \yii\debug\collectors\RequestCollector::class,
            'router' => \yii\debug\collectors\RouterCollector::class,
            'user' => \yii\debug\collectors\UserCollector::class,
        ];
    }

    /**
     * Returns the built-in panel configurations, ordered as the request itself unfolds.
     *
     * The primary navigation lists Request, Logs, Events, Profiling, Database, Mail, Queue, User, Dump, and Asset
     * Bundles. `config` opens the list but is surfaced through the brand bar rather than the panel nav; `router` only
     * feeds Request.
     *
     * {@see CoreDefinitions::merge()} drops a built-in whose optional package is not installed (`queue` without
     * `yii\queue\Queue`). {@see ProviderCatalog} contributes the panel of every optional provider package the
     * application installed; those panels finish the list and are grouped under Extensions.
     *
     * @return array<string, array<string, mixed>|class-string<Panel>|class-string<PortablePanel>|string> Panel
     * definitions indexed by panel id.
     */
    protected function corePanels(): array
    {
        return [
            'config' => \yii\debug\panels\ConfigPanel::class,
            'request' => \yii\debug\panels\RequestPanel::class,
            'log' => \yii\debug\panels\LogPanel::class,
            'event' => \yii\debug\panels\EventPanel::class,
            'profiling' => \yii\debug\panels\ProfilingPanel::class,
            'db' => \yii\debug\panels\DbPanel::class,
            'mail' => \yii\debug\panels\MailPanel::class,
            'queue' => \yii\debug\panels\QueuePanel::class,
            'router' => ['class' => \yii\debug\panels\RouterPanel::class, 'standalone' => false],
            'user' => \yii\debug\panels\UserPanel::class,
            'dump' => \yii\debug\panels\DumpPanel::class,
            'asset' => \yii\debug\panels\AssetPanel::class,
            ...ProviderCatalog::packaged()->panels(),
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
     * @throws InvalidConfigException when object creation fails for a resolvable action-map entry.
     *
     * @return Action|null Resolved action, or `null` when the route matches none.
     */
    #[Override]
    protected function createStandaloneAction(string $route): Action|null
    {
        $action = $this
            ->service(
                StandaloneActionResolver::class,
                fn(): StandaloneActionResolver => new StandaloneActionResolver($this),
            )
            ->resolve($route);

        return $action ?? parent::createStandaloneAction($route);
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
     * Hands the built-in, panel-declared, and configured standalone actions to {@see StandaloneActionResolver} and
     * stores the merged map on {@see \yii\base\Module::$actionMap}.
     *
     * @throws InvalidConfigException when the registered resolver definition is invalid.
     */
    protected function initActionMap(): void
    {
        $this->actionMap = $this
            ->service(
                StandaloneActionResolver::class,
                fn(): StandaloneActionResolver => new StandaloneActionResolver($this),
            )
            ->map($this->coreActionMap(), $this->panels, $this->actionMap);
    }

    /**
     * Hands the built-in and configured collector definitions to {@see CollectorRegistrar} and stores the resolved
     * collectors with the coordinator driving them.
     *
     * Each resolved collector is instrumented right away: {@see initPanels()} runs afterwards and a panel constructor
     * may already hit the framework, so instrumentation installed only at {@see Application::EVENT_BEFORE_REQUEST}
     * would miss the debugger's own bootstrap work.
     *
     * @throws InvalidConfigException When a collector configuration or ID is invalid.
     */
    protected function initCollectors(): void
    {
        $coordinator = $this
            ->service(CollectorRegistrar::class, fn(): CollectorRegistrar => new CollectorRegistrar($this))
            ->register($this->coreCollectors(), $this->collectors);

        $this->collectors = array_values($coordinator->collectors());
        $this->collectorCoordinator = $coordinator;
    }

    /**
     * Hands the built-in and configured panel definitions to {@see PanelRegistrar} and stores the resolved panels
     * with the catalog describing their display order.
     *
     * @throws InvalidConfigException when a panel configuration, a registration option, or the resolved catalog is
     * invalid.
     */
    protected function initPanels(): void
    {
        $catalog = $this
            ->service(PanelRegistrar::class, fn(): PanelRegistrar => new PanelRegistrar($this))
            ->register($this->corePanels(), $this->panels);

        $this->panels = $catalog->panels;
        $this->panelRegistry = $catalog->registry;
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
     * Resolves a module-scoped service from the service locator, building the definition registered on this module or
     * falling back to the default factory.
     *
     * A class name, a configuration array carrying `class`, and a callable are all built with this module bound to
     * the `module` argument, so a definition declaring `Module $module` receives it and one declaring nothing is left
     * untouched; a ready-made instance is registered as is. Only definitions registered on this module are consulted,
     * never those a parent module carries under the same ID.
     *
     * @template T of object
     *
     * @param class-string<T> $class Service class, also used as the locator ID.
     * @param Closure(): T $factory Factory building the default instance when no definition is registered.
     *
     * @throws InvalidConfigException when the registered definition does not resolve to `$class`.
     *
     * @return T Resolved service.
     */
    private function service(string $class, Closure $factory): object
    {
        if (!isset($this->getComponents(false)[$class])) {
            /** @var array{class?: class-string, __class?: class-string, ...}|class-string|Closure|object $definition */
            $definition = $this->getComponents()[$class] ?? $factory;

            if (!is_object($definition) || $definition instanceof Closure) {
                $definition = Yii::createObject($definition, ['module' => $this]);
            }

            $this->set($class, $definition);
        }

        $service = $this->get($class);

        if (!$service instanceof $class) {
            throw new InvalidConfigException(
                Message::SERVICE_INSTANCE_INVALID->getMessage($class),
            );
        }

        return $service;
    }

    /**
     * Resolves the presenter that renders the toolbar and writes the debug response headers.
     *
     * @throws InvalidConfigException when the registered {@see ToolbarPresenter} definition is invalid.
     *
     * @return ToolbarPresenter Resolved toolbar presenter.
     */
    private function toolbarPresenter(): ToolbarPresenter
    {
        return $this->service(ToolbarPresenter::class, fn(): ToolbarPresenter => new ToolbarPresenter($this));
    }
}
