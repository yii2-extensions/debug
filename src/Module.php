<?php

declare(strict_types=1);

namespace yii\debug;

use Throwable;
use Yii;
use yii\base\Action;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\IpHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\log\Dispatcher;
use yii\rbac\BaseManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\View;

use function array_merge;
use function file_get_contents;
use function gethostbyname;
use function in_array;
use function is_array;
use function is_callable;
use function is_numeric;
use function is_string;
use function microtime;
use function number_format;
use function strncmp;
use function strpos;

/**
 * Debug Module provides the debug toolbar and debugger.
 */
class Module extends \yii\base\Module implements BootstrapInterface
{
    public const string DEFAULT_IDE_TRACELINE = '<a href="ide://open?url=file://{file}&line={line}">{text}</a>';
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
     * RBAC access checker — component id or fully configured manager.
     *
     * @var BaseManager|array<string, mixed>|string
     */
    public BaseManager|array|string $authManager = 'authManager';
    /**
     * Callback evaluated by {@see checkAccess()}. Receives the current {@see Action} (or `null`) and must return `true`
     * to grant access.
     *
     * @var (callable(Action|null): bool)|null
     */
    public mixed $checkAccessCallback = null;
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
     * @var LogTarget|array<string, mixed>|string
     */
    public LogTarget|array|string $logTarget = 'yii\debug\LogTarget';
    /**
     * Page title — literal string or a callable receiving the base URL and returning a string.
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
     * Trace-line template — placeholder string ({file}, {line}, {text}), callable returning the rendered line, or
     * `false` to disable trace-line rendering entirely.
     *
     * @var (callable(array<string, mixed>, Panel): string)|string|false
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

    private static string|null $_toolbarScript = null;
    private static string $_yiiLogo = 'data:image/png;base64,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';

    /**
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
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
            // Accessing the toolbar remotely is normal — do not throw.
            return false;
        }

        throw new ForbiddenHttpException('You are not allowed to access this page.');
    }

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
     * Returns the toolbar HTML — a `<yii-debug-toolbar>` custom element wired with data attributes the bundled JS reads.
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

        return Html::tag(
            'yii-debug-toolbar',
            '',
            [
                'id' => 'yii-debug-toolbar',
                'data-url' => $url,
                'data-skip-urls' => Json::encode($skipAjaxRequestUrl),
                'data-position' => $this->toolbarPosition,
                'data-height' => $this->defaultHeight,
                'style' => 'display:none',
            ],
        );
    }

    /**
     * Returns the logo URL to be used in `<img src="`.
     */
    public static function getYiiLogo(): string
    {
        return self::$_yiiLogo;
    }

    /**
     * Returns the page title to be used in HTML.
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
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        $alias = Yii::getAlias($this->dataPath);
        $this->dataPath = $alias;

        $this->initPanels();
    }

    /**
     * Renders the mini-toolbar at the end of the page body.
     *
     * @throws Throwable
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

        // Cache the inline toolbar script per request only (`YII_DEBUG` short-circuits the static cache so dev workflows
        // pick up edits without a server restart). In production the static cache amortises the file read across
        // requests handled by the same worker.
        if (self::$_toolbarScript === null || YII_DEBUG) {
            $contents = file_get_contents(__DIR__ . '/assets/dist/js/toolbar.js');
            self::$_toolbarScript = $contents === false ? '' : $contents;
        }

        // echo is used in order to support cases where asset manager is not available
        echo '<script>' . self::$_toolbarScript . '</script>';
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
     * Sets the logo URL to be used in `<img src="`.
     */
    public static function setYiiLogo(string $logo): void
    {
        self::$_yiiLogo = $logo;
    }

    /**
     * Checks if the current user is allowed to access the module.
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
                Yii::warning('Access to debugger is denied due to checkAccessCallback.', __METHOD__);
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function corePanels(): array
    {
        // Ordered by importance for the typical request-debugging workflow:
        // identity (config) → current request → routing → logs → DB → perf → events →
        //   auth → side-effects (mail/queue) → dev-time helpers (dump) → infrastructure (assets).
        return [
            'config' => ['class' => 'yii\debug\panels\ConfigPanel'],
            'request' => ['class' => 'yii\debug\panels\RequestPanel'],
            'router' => ['class' => 'yii\debug\panels\RouterPanel'],
            'log' => ['class' => 'yii\debug\panels\LogPanel'],
            'db' => ['class' => 'yii\debug\panels\DbPanel'],
            'profiling' => ['class' => 'yii\debug\panels\ProfilingPanel'],
            'timeline' => ['class' => 'yii\debug\panels\TimelinePanel'],
            'event' => ['class' => 'yii\debug\panels\EventPanel'],
            'user' => ['class' => 'yii\debug\panels\UserPanel'],
            'mail' => ['class' => 'yii\debug\panels\MailPanel'],
            'queue' => ['class' => 'yii\debug\panels\QueuePanel'],
            'dump' => ['class' => 'yii\debug\panels\DumpPanel'],
            'asset' => ['class' => 'yii\debug\panels\AssetPanel'],
        ];
    }

    protected function defaultVersion(): string
    {
        return self::VERSION;
    }

    /**
     * @throws InvalidConfigException
     */
    protected function initPanels(): void
    {
        // merge custom panels and core panels so that they are ordered mainly by custom panels
        if ($this->panels === []) {
            $merged = $this->corePanels();
        } else {
            $corePanels = $this->corePanels();

            foreach ($corePanels as $id => $config) {
                if (isset($this->panels[$id])) {
                    unset($corePanels[$id]);
                }
            }

            $merged = array_merge($corePanels, $this->panels);
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
     * Resets potentially incompatible global settings done in app config.
     */
    protected function resetGlobalSettings(): void
    {
        Yii::$app->assetManager->bundles = [];
    }

    /**
     * @param Panel|array<string, mixed>|string $config
     *
     * @throws InvalidConfigException
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

    private function logTargetOrFail(): LogTarget
    {
        if (!$this->logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                'Debug module logTarget has not been bootstrapped — call Module::bootstrap() first.',
            );
        }

        return $this->logTarget;
    }

    private function matchesAllowedHost(string $ip): bool
    {
        foreach ($this->allowedHosts as $hostname) {
            if (gethostbyname($hostname) === $ip) {
                return true;
            }
        }

        return false;
    }

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
     * @throws InvalidConfigException
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
            throw new InvalidConfigException('Debug module logTarget must resolve to a yii\\debug\\LogTarget instance.');
        }

        return $target;
    }
}
