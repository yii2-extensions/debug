<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use Exception;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use ReflectionMethod;
use yii\base\InvalidConfigException;
use yii\debug\collectors\Collector;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\stub\LifecycleCollector;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for the {@see \yii\debug\collectors\Collector} base class covering the idempotent lifecycle, the
 * log-message stringification, and the missing-log-target contract.
 *
 * {@see VisibilityProvider} for method contract data providers.
 */
#[Group('collector')]
final class CollectorTest extends TestCase
{
    /**
     * @param class-string $class
     * @param 'protected'|'public' $expected
     */
    #[DataProviderExternal(VisibilityProvider::class, 'collectorContracts')]
    public function testExtensionMethodKeepsDeclaredVisibility(string $class, string $method, string $expected): void
    {
        if ($method === 'start' || $method === 'stop') {
            (new ReflectionMethod($class, $method))->invoke(new LifecycleCollector());
        }

        self::assertMethodVisibility($class, $method, $expected);
    }

    public function testGetLogMessagesDefaultLevelIsZero(): void
    {
        $levels = (new ReflectionMethod(Collector::class, 'getLogMessages'))->getParameters()[0]
            ?? self::fail('Expected the logger level parameter.');

        self::assertSame(
            0,
            $levels->getDefaultValue(),
            'The default logger level filter must keep every level.',
        );
    }

    public function testGetLogMessagesExportsArrayPayload(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $logTarget = new LogTarget($module);

        $logTarget->messages = [[['a' => 1], Logger::LEVEL_INFO, 'app', 0.0, []]];

        $collector = new LifecycleCollector();

        $collector->module = $module;

        $messages = $this->invoke(
            $collector,
            'getLogMessages',
            [0],
        );

        self::assertIsArray(
            $messages,
            'Must return an array.',
        );

        $first = $messages[0] ?? self::fail('Expected the exported tuple.');

        self::assertIsArray(
            $first,
            'Filtered entry must be a log tuple.',
        );
        self::assertArrayHasKey(
            0,
            $first,
            'Filtered tuple must contain a payload.',
        );
        self::assertSame(
            <<<'TEXT'
            [
                'a' => 1,
            ]
            TEXT,
            $first[0],
            'Array payload must be exported once at the adapter boundary.',
        );
    }

    public function testGetLogMessagesStringifiesThrowableFirstElement(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $logTarget = new LogTarget($module);

        $logTarget->messages = [[new Exception('boom'), Logger::LEVEL_ERROR, 'app', 0.0, []]];

        $collector = new LifecycleCollector();

        $collector->module = $module;

        $messages = $this->invoke(
            $collector,
            'getLogMessages',
            [0, [], []],
        );

        self::assertIsArray(
            $messages,
            'Filtered messages must form a list of log entries.',
        );
        self::assertCount(
            1,
            $messages,
            'Single throwable message must round-trip into the filtered list.',
        );

        $first = $messages[0] ?? self::fail('Expected the stringified tuple.');

        self::assertIsArray(
            $first,
            'Filtered entry must be a log tuple.',
        );
        self::assertIsString(
            $first[0] ?? null,
            'Throwable first element must be cast to its string form.',
        );
        self::assertStringContainsString(
            'boom',
            $first[0],
            'Stringified throwable must retain its message text.',
        );
        self::assertSame(
            0,
            $first[5] ?? null,
            'Omitted logger memory must normalize to zero.',
        );
    }

    public function testStartupAndShutdownRunTheirHooksOnce(): void
    {
        $collector = new LifecycleCollector();

        $collector->startup();
        $collector->startup();

        self::assertSame(
            1,
            $collector->startCalls,
            'Repeated startup must run the start hook once.',
        );

        $collector->shutdown();
        $collector->shutdown();

        self::assertSame(
            1,
            $collector->stopCalls,
            'Repeated shutdown must run the stop hook once.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetIsMissing(): void
    {
        $collector = new LifecycleCollector();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::LOG_TARGET_NOT_INITIALIZED_FOR_READING->getMessage(),
        );

        $this->invoke($collector, 'getLogTarget');
    }
}
