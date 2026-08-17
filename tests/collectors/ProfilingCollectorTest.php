<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\collectors\ProfilingCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ProfilingCollector} covering the profile capture totals and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('profile')]
final class ProfilingCollectorTest extends TestCase
{
    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new ProfilingCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsTypedPayloadWithMemoryAndTime(): void
    {
        $collector = $this->makeCollector();

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');
        self::assertGreaterThan(
            0,
            $snapshot->memory,
            "'memory' must reflect a positive peak.",
        );
        self::assertGreaterThanOrEqual(
            0.0,
            $snapshot->time,
            "'time' must be non-negative.",
        );
        self::assertSame(
            [],
            $snapshot->entries(),
            'Empty log target yields no profile blocks.',
        );
        self::assertSame(
            [],
            $snapshot->samples(),
            'Empty log target yields no memory samples.',
        );
    }

    public function testIdPairsWithTheProfilingPanel(): void
    {
        self::assertSame(
            'profiling',
            (new ProfilingCollector())->id(),
            "Stable ID must be 'profiling'.",
        );
    }

    /**
     * Creates a started collector wired to a debug module on top of a mocked web application.
     *
     * @return ProfilingCollector Started collector.
     */
    private function makeCollector(): ProfilingCollector
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = new ProfilingCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }
}
