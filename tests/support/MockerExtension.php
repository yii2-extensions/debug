<?php

declare(strict_types=1);

namespace yii\debug\tests\support;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use ReflectionClass;
use Xepozz\InternalMocker\Mocker;
use Xepozz\InternalMocker\MockerState;

/**
 * PHPUnit extension that swaps PHP built-in functions inside production namespaces.
 *
 * Lets unit tests cover the defensive branches that depend on `preg_replace()` returning `null`, `is_string()` /
 * `is_iterable()` returning `false`, and similar guards that Yii's runtime contracts make unreachable under normal
 * fixtures.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 *
 * @since 0.1
 */
final class MockerExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(
            new class implements StartedSubscriber {
                public function notify(Started $event): void
                {
                    MockerExtension::load();
                }
            },
            new class implements PreparationStartedSubscriber {
                public function notify(PreparationStarted $event): void
                {
                    MockerState::resetState();
                    MockerExtension::resetDefaults();
                }
            },
        );
    }

    public static function load(): void
    {
        $mocks = [
            ['namespace' => 'yii\debug\models\router', 'name' => 'preg_replace'],
            ['namespace' => 'yii\debug\models\router', 'name' => 'is_iterable'],
            ['namespace' => 'yii\debug\models\router', 'name' => 'is_string'],
            ['namespace' => 'yii\debug\models\router', 'name' => 'count'],
            ['namespace' => 'yii\debug\widgets\phpinfo', 'name' => 'function_exists'],
        ];

        (new Mocker(stubPath: __DIR__ . '/mocker-stubs.php'))->load($mocks);

        MockerState::saveState();
    }

    /**
     * Clears `MockerState::$defaults` via reflection — `MockerState::resetState()` only resets `$state`, leaving
     * defaults registered with `addCondition(..., $default: true)` sticky across tests.
     */
    public static function resetDefaults(): void
    {
        $defaults = (new ReflectionClass(MockerState::class))->getProperty('defaults');
        $defaults->setValue(null, []);
    }
}
