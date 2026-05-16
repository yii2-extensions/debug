<?php

declare(strict_types=1);

namespace yii\debug\tests\support;

use ReflectionClass;
use Yii;
use yii\base\Application;
use yii\debug\{LogTarget, Module, Panel};
use yii\di\Container;
use yii\helpers\ArrayHelper;
use yii\web\Controller;

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
     * Destroys the active application by clearing `Yii::$app` and resetting the DI container.
     */
    protected function destroyApplication(): void
    {
        (new ReflectionClass(Yii::class))->setStaticPropertyValue('app', null);
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
     * Sets an inaccessible object property to a designated value, walking the inheritance chain when the property is
     * declared on a parent class.
     */
    protected function setInaccessibleProperty(object $object, string $propertyName, mixed $value): void
    {
        $this->resolveReflectionProperty($object, $propertyName)->setValue($object, $value);
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

    private function resolveReflectionProperty(object $object, string $propertyName): \ReflectionProperty
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
