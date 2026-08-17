<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\collectors\LogCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see LogCollector} covering the log capture, the router-category exclusion, and the
 * startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('log')]
final class LogCollectorTest extends TestCase
{
    public function testCaptureExcludesCategoriesOwnedByRouterCollector(): void
    {
        $collector = $this->makeCollector();

        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = [
            ['kept', Logger::LEVEL_INFO, 'application', 0.0, [], 0],
            ['dropped', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
        ];

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');
        self::assertCount(
            1,
            $snapshot->entries(),
            'Router categories must be filtered.',
        );
    }

    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        self::assertNull(
            (new LogCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsTypedRows(): void
    {
        $collector = $this->makeCollector();

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');
        self::assertSame(
            [],
            $snapshot->entries(),
            'Empty log target yields no rows.',
        );
    }

    public function testIdPairsWithTheLogsPanel(): void
    {
        self::assertSame(
            'log',
            (new LogCollector())->id(),
            "Stable ID must be 'log'.",
        );
    }

    /**
     * Creates a started collector wired to a debug module on top of a mocked web application.
     *
     * @return LogCollector Started collector.
     */
    private function makeCollector(): LogCollector
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = new LogCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }
}
