<?php

declare(strict_types=1);

namespace yiiunit\debug;

use ReflectionClass;
use Yii;
use yii\base\Application;
use yii\di\Container;
use yii\helpers\ArrayHelper;

/**
 * Base class for the debug-extension test suite.
 *
 * Provides Yii application bootstrapping (web + console), automatic teardown, and a reflection
 * helper for invoking non-public methods inside fixtures.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 2.1.29
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Destroys the active application by clearing `Yii::$app` and resetting the DI container.
     */
    protected function destroyApplication(): void
    {
        Yii::$app = null;
        Yii::$container = new Container();
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
     * Populates `Yii::$app` with a new console application instance.
     *
     * @param array<string, mixed> $config Extra application configuration merged on top of the defaults.
     * @param class-string<Application> $appClass Application class to instantiate.
     */
    protected function mockApplication(array $config = [], string $appClass = \yii\console\Application::class): void
    {
        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'vendorPath' => dirname(__DIR__) . '/vendor',
        ], $config));
    }

    /**
     * Populates `Yii::$app` with a new web application instance pre-wired with a request component.
     *
     * @param array<string, mixed> $config Extra application configuration merged on top of the defaults.
     * @param class-string<Application> $appClass Application class to instantiate.
     */
    protected function mockWebApplication(array $config = [], string $appClass = \yii\web\Application::class): void
    {
        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'vendorPath' => dirname(__DIR__) . '/vendor',
            'components' => [
                'request' => [
                    'cookieValidationKey' => 'wefJDF8sfdsfSDefwqdxj9oq',
                    'scriptFile' => __DIR__ . '/index.php',
                    'scriptUrl' => '/index.php',
                ],
            ],
        ], $config));
    }
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->destroyApplication();
    }
}
