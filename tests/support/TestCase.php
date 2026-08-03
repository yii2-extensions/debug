<?php

declare(strict_types=1);

namespace yii\debug\tests\support;

use ReflectionClass;
use ReflectionProperty;
use Yii;
use yii\base\Application;
use yii\debug\{LogTarget, Module, Panel};
use yii\debug\storage\{
    DebugSnapshot,
    PanelFailure,
    PanelSnapshot,
    RequestSummary,
    SnapshotStore,
};
use yii\di\Container;
use yii\helpers\ArrayHelper;
use yii\web\{Controller, Session};

/**
 * Base class for the debug-extension test suite.
 *
 * Provides Yii application bootstrapping (web + console), automatic teardown, and a reflection helper for invoking
 * non-public methods inside fixtures.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array<int|string, mixed>|null Snapshot of `$_SERVER` taken on the first test's `setUp` so `tearDown` can
     * restore the bootstrap-time state (`REQUEST_TIME_FLOAT`, `argv`, `argc`, `SCRIPT_FILENAME`, etc.).
     */
    private static array|null $serverSnapshot = null;

    /**
     * Destroys the active application by closing its session, clearing `Yii::$app`, and resetting the DI container.
     */
    protected function destroyApplication(): void
    {
        $yii = new ReflectionClass(Yii::class);

        $app = $yii->getStaticPropertyValue('app');

        if ($app instanceof Application && $app->has('session', true)) {
            $session = $app->get('session', false);

            if ($session instanceof Session && $session->getIsActive()) {
                $session->removeAll();
                $session->close();
            }
        }

        $yii->setStaticPropertyValue('app', null);

        Yii::$container = new Container();
    }

    /**
     * Reads an inaccessible object property, walking the inheritance chain when the property is declared on a parent
     * class.
     */
    protected function getInaccessibleProperty(object $object, string $propertyName): mixed
    {
        return $this->resolveReflectionProperty($object, $propertyName)->getValue($object);
    }

    protected function hydratePanel(Panel $panel, PanelSnapshot $snapshot): void
    {
        $panel->hydrate($snapshot->jsonSerialize());
    }

    /**
     * Invokes a non-public method on the given object via reflection and returns its result.
     *
     * @param array<int, mixed> $args Arguments forwarded to the method.
     */
    protected function invoke(object $object, string $method, array $args = []): mixed
    {
        $methodReflection = (new ReflectionClass($object))->getMethod($method);

        return $methodReflection->invokeArgs($object, $args);
    }

    /**
     * Invokes a non-public static method on the given class via reflection and returns its result.
     *
     * @param class-string $className FQCN declaring the static method.
     * @param array<int, mixed> $args Arguments forwarded to the method.
     */
    protected function invokeStatic(string $className, string $method, array $args = []): mixed
    {
        return (new ReflectionClass($className))->getMethod($method)->invoke(null, ...$args);
    }

    /**
     * Builds a fully-wired panel with a Yii web app, debug module, log target, asset manager, and a stub controller.
     *
     * @template T of Panel
     *
     * @param class-string<T> $panelClass FQCN of the panel to instantiate.
     * @param array<string, mixed> $components Extra components merged into the web app config (for example, `db`).
     *
     * @return T Instance with all dependencies satisfied.
     */
    protected function makePanel(string $panelClass, array $components = []): Panel
    {
        $assetPath = dirname(__DIR__, 2) . '/runtime/assets';

        @mkdir($assetPath, 0o777, true);

        $this->mockWebApplication(
            [
                'components' => ArrayHelper::merge(
                    [
                        'assetManager' => [
                            'basePath' => $assetPath,
                            'baseUrl' => '/assets',
                        ],
                    ],
                    $components,
                ),
            ],
        );

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);

        $panel = new $panelClass();
        $panel->module = $module;

        return $panel;
    }

    /**
     * Populates `Yii::$app` with a new console application instance.
     *
     * @param array<string, mixed> $config Extra application configuration merged on top of the defaults.
     * @param class-string<Application> $appClass Application class to instantiate.
     */
    protected function mockApplication(array $config = [], string $appClass = \yii\console\Application::class): void
    {
        new $appClass(
            ArrayHelper::merge(
                [
                    'id' => 'testapp',
                    'basePath' => dirname(__DIR__, 2),
                    'runtimePath' => dirname(__DIR__, 2) . '/runtime',
                    'vendorPath' => dirname(__DIR__, 2) . '/vendor',
                ],
                $config,
            ),
        );
    }

    /**
     * Populates `Yii::$app` with a new web application instance pre-wired with a request component.
     *
     * @param array<string, mixed> $config Extra application configuration merged on top of the defaults.
     * @param class-string<Application> $appClass Application class to instantiate.
     */
    protected function mockWebApplication(array $config = [], string $appClass = \yii\web\Application::class): void
    {
        new $appClass(
            ArrayHelper::merge(
                [
                    'id' => 'testapp',
                    'basePath' => dirname(__DIR__, 2),
                    'runtimePath' => dirname(__DIR__, 2) . '/runtime',
                    'vendorPath' => dirname(__DIR__, 2) . '/vendor',
                    'components' => [
                        'request' => [
                            'cookieValidationKey' => 'wefJDF8sfdsfSDefwqdxj9oq',
                            'scriptFile' => __DIR__ . '/index.php',
                            'scriptUrl' => '/index.php',
                        ],
                    ],
                ],
                $config,
            ),
        );
    }

    /**
     * Builds a complete request-summary DTO with concise per-test overrides.
     *
     * @param array<string, mixed> $overrides
     */
    protected function requestSummary(string $tag = 'tag-1', array $overrides = []): RequestSummary
    {
        return RequestSummary::fromArray(
            array_replace(
                [
                    'tag' => $tag,
                    'url' => 'dummy',
                    'ajax' => false,
                    'method' => 'GET',
                    'ip' => '127.0.0.1',
                    'time' => 1_700_000_000.0,
                    'statusCode' => 200,
                    'sqlCount' => 0,
                    'excessiveCallersCount' => 0,
                    'mailCount' => 0,
                    'mailFiles' => [],
                    'processingTime' => null,
                    'peakMemory' => null,
                ],
                $overrides,
            ),
        );
    }

    /**
     * Sets an inaccessible object property to a designated value, walking the inheritance chain when the property is
     * declared on a parent class.
     */
    protected function setInaccessibleProperty(object $object, string $propertyName, mixed $value): void
    {
        $this->resolveReflectionProperty($object, $propertyName)->setValue($object, $value);
    }

    /**
     * Sets an inaccessible static property on the given class to a designated value.
     *
     * @param class-string $className FQCN of the class declaring the static property.
     */
    protected function setInaccessibleStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        (new ReflectionClass($className))->setStaticPropertyValue($propertyName, $value);
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$serverSnapshot ??= $_SERVER;

        $_GET = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->destroyApplication();

        $_SERVER = self::$serverSnapshot ?? [];
        $_GET = [];
    }

    /**
     * Persists a complete version-3 JSON fixture through the production storage boundary.
     *
     * @param array<string, PanelSnapshot> $panels
     * @param array<string, mixed> $summary
     * @param array<string, \Throwable> $failures
     */
    protected function writeDebugSnapshot(
        Module $module,
        string $tag,
        array $panels,
        array $summary = [],
        array $failures = [],
    ): void {
        $payloads = [];

        foreach ($panels as $id => $snapshot) {
            $payloads[$id] = $snapshot->jsonSerialize();
        }

        $requestSummary = $this->requestSummary($tag, $summary);
        $store = new SnapshotStore(Yii::getAlias($module->dataPath), $module->dirMode, $module->fileMode);
        $panelFailures = [];

        foreach ($failures as $id => $throwable) {
            $panelFailures[$id] = PanelFailure::fromThrowable(PanelFailure::CAPTURE, $throwable);
        }

        $store->writeSnapshot($tag, new DebugSnapshot($requestSummary, $payloads, $panelFailures));
        $store->updateManifest($requestSummary, $module->historySize);
    }

    private function resolveReflectionProperty(object $object, string $propertyName): ReflectionProperty
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $parent = $class->getParentClass();

            if ($parent === false) {
                self::fail(
                    "Property '{$propertyName}' not found on '{$class->getName()}' or its ancestors.",
                );
            }

            $class = $parent;
        }

        return $class->getProperty($propertyName);
    }
}
