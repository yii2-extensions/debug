<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\collectors\TimelineCollector;
use yii\debug\tests\support\TestCase;

use function microtime;

/**
 * Unit tests for {@see TimelineCollector} covering the request-boundary capture and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('timeline')]
final class TimelineCollectorTest extends TestCase
{
    public function testCaptureCapturesStartEndAndMemory(): void
    {
        $collector = $this->makeCollector();

        $_SERVER['REQUEST_TIME_FLOAT'] = 1_700_000_000.0;

        $snapshot = $this->captureSnapshot($collector);

        self::assertEqualsWithDelta(
            1_700_000_000.0,
            $snapshot->start,
            1e-3,
            'Start must echo the request time.',
        );
        self::assertGreaterThanOrEqual(
            $snapshot->start,
            $snapshot->end,
            'End must be greater than or equal to start.',
        );
        self::assertGreaterThan(
            0,
            $snapshot->memory,
            'Memory peak must be positive.',
        );
    }

    public function testCaptureFallsBackToMicrotimeWhenRequestTimeFloatMissing(): void
    {
        $collector = $this->makeCollector();

        unset($_SERVER['REQUEST_TIME_FLOAT']);

        $before = microtime(true);

        $snapshot = $this->captureSnapshot($collector);

        $after = microtime(true);

        self::assertGreaterThanOrEqual(
            $before,
            $snapshot->start,
            "Start must fall back to 'microtime(true)'.",
        );
        self::assertLessThanOrEqual(
            $after,
            $snapshot->start,
            'Start must not jump past the call site.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new TimelineCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testIdPairsWithTheTimelinePanel(): void
    {
        self::assertSame(
            'timeline',
            (new TimelineCollector())->id(),
            "Stable ID must be 'timeline'.",
        );
    }

    /**
     * Captures the timeline snapshot, failing when the started collector produces nothing.
     *
     * @param TimelineCollector $collector Started collector.
     *
     * @return TimelineSnapshot Captured timeline snapshot.
     */
    private function captureSnapshot(TimelineCollector $collector): TimelineSnapshot
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot;
    }

    /**
     * Creates a started collector on top of a mocked web application.
     *
     * @return TimelineCollector Started collector.
     */
    private function makeCollector(): TimelineCollector
    {
        $this->mockWebApplication();

        $collector = new TimelineCollector();

        $collector->startup();

        return $collector;
    }
}
