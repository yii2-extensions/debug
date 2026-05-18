<?php

declare(strict_types=1);

namespace yii\debug;

use Throwable;
use UIAwesome\Html\Helper\Attributes;
use Yii;
use yii\base\{Action, Application, BootstrapInterface, Event, InvalidConfigException};
use yii\debug\helpers\Icon;
use yii\helpers\{IpHelper, Json, Url};
use yii\log\Dispatcher;
use yii\rbac\BaseManager;
use yii\web\{ErrorHandler, ErrorHandlerRenderEvent, ForbiddenHttpException, Response, View};

use function file_get_contents;
use function gethostbyname;
use function in_array;
use function is_array;
use function is_callable;
use function is_numeric;
use function is_string;
use function microtime;
use function number_format;
use function str_contains;
use function str_replace;
use function strncmp;
use function strpos;

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
     * Module version reported by {@see defaultVersion()}.
     */
    public const string VERSION = '0.1.0';

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
     * Namespace for the debugger controllers.
     */
    public $controllerNamespace = 'yii\debug\controllers';
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
     * Permission applied to newly created debugger directories (used by {@see chmod()}); no umask is applied.
     */
    public int $dirMode = 0o775;
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
     * Permission applied to newly created debugger data files (used by {@see chmod()}); `null` keeps the env default.
     */
    public int|null $fileMode = null;
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
     * Cached inline toolbar JS, populated lazily by {@see renderToolbar()} and {@see injectToolbarOnErrorPage()}.
     */
    private static string|null $toolbarScript = null;
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
     */
    public function beforeAction($action): bool
    {
        if (!$this->enableDebugLogs) {
            $log = $this->get('log');

            if ($log instanceof Dispatcher) {
                foreach ($log->targets as $target) {
                    $target->enabled = false;
                }
            }
        }

        if (!parent::beforeAction($action)) {
            return false;
        }

        Yii::$app->getView()->off(View::EVENT_END_BODY, [$this, 'renderToolbar']);
        Yii::$app->getResponse()->off(Response::EVENT_AFTER_PREPARE, [$this, 'setDebugHeaders']);

        if ($this->checkAccess($action)) {
            $this->resetGlobalSettings();

            return true;
        }

        if (in_array($action->id, ['toolbar', 'toolbar-data'], true)) {
            // Accessing the toolbar remotely is normal do not throw.
            return false;
        }

        throw new ForbiddenHttpException(
            'You are not allowed to access this page.',
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
            function () use ($app): void {
                $app->getResponse()->on(Response::EVENT_AFTER_PREPARE, [$this, 'setDebugHeaders']);
            },
        );
        $app->on(
            Application::EVENT_BEFORE_ACTION,
            function () use ($app): void {
                $app->getView()->on(View::EVENT_END_BODY, [$this, 'renderToolbar']);
            },
        );

        $errorHandler = $app->errorHandler;

        $errorHandler->on(\yii\base\View::EVENT_AFTER_RENDER, [$this, 'injectToolbarOnErrorPage']);

        $app->getUrlManager()->addRules(
            [
                [
                    'class' => $this->urlRuleClass,
                    'route' => $this->getUniqueId(),
                    'pattern' => $this->getUniqueId(),
                    'normalizer' => false,
                    'suffix' => false,
                ],
                [
                    'class' => $this->urlRuleClass,
                    'route' => $this->getUniqueId() . '/<controller>/<action>',
                    'pattern' => $this->getUniqueId() . '/<controller:[\w\-]+>/<action:[\w\-]+>',
                    'normalizer' => false,
                    'suffix' => false,
                ],
            ],
            false,
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
                '/' . $this->getUniqueId() . '/default/toolbar-data',
                'tag' => $logTarget->tag,
            ],
        );

        $skipAjaxRequestUrl = [];

        foreach ($this->skipAjaxRequestUrl as $key => $route) {
            if (is_string($route) || is_array($route)) {
                $skipAjaxRequestUrl[$key] = Url::to($route);
            }
        }

        return '<yii-debug-toolbar' . Attributes::render(
            [
                'id' => 'yii-debug-toolbar',
                'data-url' => $url,
                'data-skip-urls' => Json::encode($skipAjaxRequestUrl),
                'data-position' => $this->toolbarPosition,
                'data-height' => $this->defaultHeight,
                'style' => 'display:none',
            ],
        ) . '></yii-debug-toolbar>';
    }

    /**
     * Returns the Yii logo as a data URI ready to drop into `<img src="…">` or `<link rel="icon">`.
     *
     * Delegates the SVG read and sanitization to {@see Icon::render()} (which caches the result per worker) and wraps
     * the payload as a base64 data URI, so the logo is self-contained and needs neither the Asset Manager nor extra
     * HTTP requests.
     */
    public static function getYiiLogo(): string
    {
        if (self::$yiiLogo === null) {
            self::$yiiLogo = 'data:image/svg+xml;base64,' . base64_encode(Icon::render('yii'));
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
    public function init(): void
    {
        parent::init();

        $alias = Yii::getAlias($this->dataPath);

        $this->dataPath = $alias;

        $this->initPanels();
    }

    /**
     * Injects the debug toolbar into the rendered HTML of an error page (yiisoft/yii2#7616).
     *
     * Wired in {@see bootstrap()} as a listener for {@see ErrorHandler::EVENT_AFTER_RENDER}; the event fires after
     * `renderException()` produces the HTML body but before the response is sent, so handlers may rewrite the output.
     */
    public function injectToolbarOnErrorPage(ErrorHandlerRenderEvent $event): void
    {
        if (!$this->checkAccess() || Yii::$app->getRequest()->getIsAjax()) {
            return;
        }

        if (self::$toolbarScript === null || YII_DEBUG) {
            $contents = file_get_contents(__DIR__ . '/assets/dist/js/toolbar.min.js');

            self::$toolbarScript = $contents === false ? '' : $contents;
        }

        $injection = $this->getToolbarHtml() . '<script>' . self::$toolbarScript . '</script>';

        if (str_contains($event->output, '</body>')) {
            $event->output = str_replace('</body>', $injection . '</body>', $event->output);

            return;
        }

        $event->output .= $injection;
    }

    /**
     * Renders the mini-toolbar at the end of the page body.
     *
     * Wired in {@see bootstrap()} as a listener for {@see View::EVENT_END_BODY}. Caches the inline toolbar script per
     * worker; {@see YII_DEBUG} short-circuits the cache so dev edits are picked up without restarting the server.
     *
     * @throws Throwable When the view dynamic render fails for the current request.
     */
    public function renderToolbar(Event $event): void
    {
        if (!$this->checkAccess() || Yii::$app->getRequest()->getIsAjax()) {
            return;
        }

        $view = $event->sender;

        if (!$view instanceof View) {
            return;
        }

        echo $view->renderDynamic('return Yii::$app->getModule("' . $this->getUniqueId() . '")->getToolbarHtml();');

        if (self::$toolbarScript === null || YII_DEBUG) {
            $contents = file_get_contents(__DIR__ . '/assets/dist/js/toolbar.min.js');

            self::$toolbarScript = $contents === false ? '' : $contents;
        }

        echo '<script>' . self::$toolbarScript . '</script>';
    }

    /**
     * Sets headers carrying debug data on AJAX responses so the toolbar can resolve the captured tag and link back to
     * the full view.
     */
    public function setDebugHeaders(Event $event): void
    {
        if (!$this->checkAccess()) {
            return;
        }

        $logTarget = $this->logTargetOrFail();

        $url = Url::toRoute(
            [
                '/' . $this->getUniqueId() . '/default/view',
                'tag' => $logTarget->tag,
            ],
        );

        $sender = $event->sender;

        if (!$sender instanceof Response) {
            return;
        }

        $rawStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $requestStart = is_numeric($rawStart) ? (float) $rawStart : microtime(true);

        $sender->getHeaders()
            ->set('X-Debug-Tag', $logTarget->tag)
            ->set('X-Debug-Duration', number_format((microtime(true) - $requestStart) * 1000 + 1))
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

        $allowed = $this->matchesAllowedIp($ip) || $this->matchesAllowedHost($ip);

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
     * Returns the built-in panel configurations, ordered by their importance in a typical request-debugging workflow
     * (identity → request → routing → logs → DB → perf → events → auth → side effects → dev-time helpers →
     * infrastructure).
     *
     * @return array<string, array<string, mixed>> Panel configurations indexed by panel id.
     */
    protected function corePanels(): array
    {
        return [
            'config' => ['class' => \yii\debug\panels\ConfigPanel::class],
            'request' => ['class' => \yii\debug\panels\RequestPanel::class],
            'router' => ['class' => \yii\debug\panels\RouterPanel::class],
            'log' => ['class' => \yii\debug\panels\LogPanel::class],
            'db' => ['class' => \yii\debug\panels\DbPanel::class],
            'profiling' => ['class' => \yii\debug\panels\ProfilingPanel::class],
            'timeline' => ['class' => \yii\debug\panels\TimelinePanel::class],
            'event' => ['class' => \yii\debug\panels\EventPanel::class],
            'user' => ['class' => \yii\debug\panels\UserPanel::class],
            'mail' => ['class' => \yii\debug\panels\MailPanel::class],
            'queue' => ['class' => \yii\debug\panels\QueuePanel::class],
            'dump' => ['class' => \yii\debug\panels\DumpPanel::class],
            'asset' => ['class' => \yii\debug\panels\AssetPanel::class],
        ];
    }

    /**
     * Returns the default module version string.
     */
    protected function defaultVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Merges custom panels on top of the built-in core panels and instantiates each entry, dropping any panel whose
     * {@see Panel::isEnabled()} returns `false`.
     *
     * @throws InvalidConfigException When a panel configuration cannot be resolved into a {@see Panel} instance.
     */
    protected function initPanels(): void
    {
        if ($this->panels === []) {
            $merged = $this->corePanels();
        } else {
            $corePanels = $this->corePanels();

            foreach ($corePanels as $id => $config) {
                if (isset($this->panels[$id])) {
                    unset($corePanels[$id]);
                }
            }

            $merged = [...$corePanels, ...$this->panels];
        }

        $this->panels = [];

        foreach ($merged as $id => $config) {
            $panel = $this->buildPanel($id, $config);

            if ($panel === null) {
                continue;
            }

            $this->panels[$id] = $panel;

            if (!$panel->isEnabled()) {
                unset($this->panels[$id]);
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
     * Resolves a panel configuration into a {@see Panel} instance, binding `id` and `module` references.
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

            return $config;
        }

        if (is_string($config)) {
            $class = $config;
            $properties = [];
        } else {
            $class = $config['class'] ?? null;

            if (!is_string($class) || !class_exists($class)) {
                return null;
            }

            $properties = $config;

            unset($properties['class']);
        }

        $properties['module'] = $this;
        $properties['id'] = $id;

        $object = Yii::$container->get($class, [], $properties);

        return $object instanceof Panel ? $object : null;
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
                'Debug module logTarget has not been bootstrapped; call Module::bootstrap() first.',
            );
        }

        return $this->logTarget;
    }

    /**
     * Returns whether the IP matches any entry in {@see $allowedHosts} after DNS resolution.
     */
    private function matchesAllowedHost(string $ip): bool
    {
        foreach ($this->allowedHosts as $hostname) {
            if (gethostbyname($hostname) === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the IP matches any entry in {@see $allowedIPs} (exact, wildcard, or CIDR).
     */
    private function matchesAllowedIp(string $ip): bool
    {
        foreach ($this->allowedIPs as $filter) {
            if ($filter === '*' || $filter === $ip) {
                return true;
            }

            $wildcardPos = strpos($filter, '*');

            if ($wildcardPos !== false && strncmp($ip, $filter, $wildcardPos) === 0) {
                return true;
            }

            if (strpos($filter, '/') !== false && IpHelper::inRange($ip, $filter)) {
                return true;
            }
        }

        return false;
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

        if (is_string($this->logTarget)) {
            $class = $this->logTarget;

            $properties = [];
        } else {
            $class = $this->logTarget['class'] ?? LogTarget::class;

            if (!is_string($class) || !class_exists($class)) {
                throw new InvalidConfigException(
                    'Debug module logTarget configuration must declare a valid class name.',
                );
            }

            $properties = $this->logTarget;

            unset($properties['class']);
        }

        $target = Yii::$container->get($class, [$this], $properties);

        if (!$target instanceof LogTarget) {
            throw new InvalidConfigException(
                'Debug module logTarget must resolve to a yii\\debug\\LogTarget instance.',
            );
        }

        return $target;
    }
}
