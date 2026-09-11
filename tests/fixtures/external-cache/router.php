<?php

declare(strict_types=1);

// Standalone development fixture. Never include this router in a deployed application.
define('YII_DEBUG', true);
define('YII_ENV', 'dev');
define('YII_ENABLE_ERROR_HANDLER', false);
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
require $root . '/vendor/yiisoft/yii2/Yii.php';

$providers = [new \Acme\Debug\CachePanel(), new class extends \Acme\Debug\CachePanel {
    protected const string ICON = 'not-a-host-icon';
    protected const string ID = 'independent-second-cache';
    protected const string TITLE = 'Secondary cache';
}];
$logger = new \yii\log\PsrLogger(category: 'acme.cache');
$collectors = [];
foreach ($providers as $provider) {
    $logger = new \Acme\Debug\CacheCollector($provider->id(), $logger);
    $collectors[] = $logger;
}
$cache = new \Acme\Debug\Cache($logger);

$policy = new \PHPForge\Debug\Capture\CapturePolicy();
require_once $root . '/vendor/php-forge/debug/tools/fixtures/provider-events.php';
$eventServices = \Acme\ProviderEvents\Fixture::create($policy->redact(...), $policy->redactUrl(...));
foreach ($eventServices->collectors as $collector) {
    $collectors[$collector->id()] = $collector;
}
foreach ($eventServices->panels as $provider) {
    $providers[$provider->id()] = $provider;
}

$directory = sys_get_temp_dir() . '/external-cache-browser-yii2';

$path = parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
if (is_string($path) && str_starts_with($path, '/debug-assets/')) {
    $asset = realpath($directory . '/assets/' . substr($path, strlen('/debug-assets/')));
    if ($asset === false || !str_starts_with($asset, $directory . '/assets/') || !is_file($asset)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . match (pathinfo($asset, PATHINFO_EXTENSION)) {
        'css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml',
        'woff2' => 'font/woff2', default => 'application/octet-stream',
    });
    readfile($asset);
    exit;
}

if (!is_dir($directory . '/assets') && !mkdir($directory . '/assets', 0o700, true)) {
    throw new \RuntimeException('Unable to create the example asset directory.');
}
final class ExternalCacheController extends \yii\web\Controller
{
    public \Acme\Debug\Cache|null $cache = null;
    /**
     * @var (\Closure(string): void)|null
     */
    public \Closure|null $exerciseProviders = null;

    public function actionIndex(): string
    {
        if ($this->cache === null) {
            throw new \LogicException('The example cache must be configured.');
        }
        $count = ($_GET['state'] ?? '') === 'empty' ? 0 : (($_GET['state'] ?? '') === 'dense' ? 30 : 1);
        $key = ($_GET['state'] ?? '') === 'changed' ? 'changed-live-key' : 'historical-key';
        for ($i = 0; $i < $count; ++$i) {
            $this->cache->get($key . $i);
            $this->cache->set($key . $i, 'private contents');
            $this->cache->get($key . $i);
        }
        if ($this->exerciseProviders === null) {
            throw new \LogicException('The provider fixture must be configured.');
        }
        ($this->exerciseProviders)(is_string($_GET['state'] ?? null) ? $_GET['state'] : '');
        return '<!doctype html><html lang="en"><head><title>External cache client</title></head><body>Cache request captured</body></html>';
    }

    public function behaviors(): array
    {
        return [
            'access' => ['class' => \yii\filters\AccessControl::class, 'user' => false, 'rules' => [
                ['allow' => true, 'ips' => ['127.0.0.1', '::1']],
            ]],
            'verbs' => ['class' => \yii\filters\VerbFilter::class, 'actions' => ['index' => ['GET']]],
        ];
    }
}

$app = new \yii\web\Application([
    'id' => 'external-cache',
    'basePath' => __DIR__,
    'vendorPath' => $root . '/vendor',
    'runtimePath' => $directory,
    'bootstrap' => ['debug'],
    'defaultRoute' => 'cache/index',
    'components' => [
        'request' => ['enableCookieValidation' => false, 'scriptUrl' => '/index.php', 'baseUrl' => ''],
        'assetManager' => ['basePath' => $directory . '/assets', 'baseUrl' => '/debug-assets'],
        'urlManager' => ['enablePrettyUrl' => true, 'showScriptName' => false],
    ],
    'modules' => [
        'debug' => [
            'class' => \yii\debug\Module::class,
            'dataPath' => $directory . '/captures',
            'allowedIPs' => ['127.0.0.1', '::1'],
            'collectors' => $collectors,
            'panels' => ($_GET['_fixture_raw'] ?? '') === '1' ? [] : $providers,
        ],
    ],
    'controllerMap' => [
        'cache' => ['class' => ExternalCacheController::class, 'cache' => $cache, 'exerciseProviders' => $eventServices->run],
    ],
]);
$app->run();
