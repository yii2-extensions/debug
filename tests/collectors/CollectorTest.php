<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\stub\LifecycleCollector;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for the {@see \yii\debug\collectors\Collector} base class covering the idempotent lifecycle, the
 * log-message stringification, and the missing-log-target contract.
 */
#[Group('collector')]
final class CollectorTest extends TestCase
{
    public function testGetLogMessagesStringifiesThrowableFirstElement(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $logTarget = new LogTarget($module);

        $logTarget->messages = [[new Exception('boom'), Logger::LEVEL_ERROR, 'app', 0.0]];

        $collector = new LifecycleCollector();

        $collector->module = $module;

        $messages = $this->invoke(
            $collector,
            'getLogMessages',
            [0, [], [], true],
        );

        self::assertIsArray(
            $messages,
            "'getLogMessages' must return a list of log entries.",
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
            'The debug module logTarget must be initialized',
        );

        $this->invoke($collector, 'getLogTarget');
    }
}
