<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\history;

use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPUnit\Framework\Attributes\{Group, Test};
use RuntimeException;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\{HistoryComparison, HistoryMetricComparison, HistoryPanelComparison};

/**
 * Unit tests for capture comparisons across request summaries and privacy-preserving panel fingerprints.
 */
#[Group('history')]
final class HistoryComparisonTest extends TestCase
{
    #[Test]
    public function fromSnapshotsComputesMetricAndPanelDifferencesWithoutExposingValues(): void
    {
        $baseline = new DebugSnapshot(
            self::summary(
                'baseline',
                processingTime: 0.010,
                peakMemory: 1_048_576,
                sqlCount: 2,
            ),
            [
                'request' => [
                    'headers' => ['accept' => 'application/json'],
                    'items' => [1, 2],
                    'removed' => 'sensitive-baseline-value',
                ],
            ],
            [],
        );
        $target = new DebugSnapshot(
            self::summary(
                'target',
                processingTime: 0.015,
                peakMemory: 2_097_152,
                sqlCount: 5,
            ),
            [
                'request' => [
                    'headers' => ['accept' => 'application/xml'],
                    'items' => [1, 2, 3],
                    'added' => 'sensitive-target-value',
                ],
            ],
            [],
        );

        $comparison = HistoryComparison::fromSnapshots(
            $baseline,
            $target,
            ['request' => 'Request'],
        );

        self::assertTrue(
            $comparison->hasDifferences(),
            'Different summary and panel data must mark the comparison as changed.',
        );
        $duration = null;
        $sqlQueries = null;

        foreach ($comparison->metrics as $metric) {
            if ($metric->label === 'Duration') {
                $duration = $metric;
            }

            if ($metric->label === 'SQL queries') {
                $sqlQueries = $metric;
            }
        }

        self::assertInstanceOf(
            HistoryMetricComparison::class,
            $duration,
            'Duration comparison must be present.',
        );
        self::assertSame(
            '+5.00 ms (+50.0%)',
            $duration->delta,
            'Duration must expose the absolute and relative target delta.',
        );
        self::assertInstanceOf(
            HistoryMetricComparison::class,
            $sqlQueries,
            'SQL-query comparison must be present.',
        );
        self::assertSame(
            '+3 (+150.0%)',
            $sqlQueries->delta,
            'Integer metrics must expose the absolute and relative target delta.',
        );

        $panel = null;

        foreach ($comparison->panels as $candidate) {
            if ($candidate->id === 'request') {
                $panel = $candidate;
                break;
            }
        }

        self::assertInstanceOf(
            HistoryPanelComparison::class,
            $panel,
            'Request panel comparison must be present.',
        );

        self::assertSame(
            'Request',
            $panel->label,
            'Configured panel labels must be retained.',
        );
        self::assertSame(
            2,
            $panel->added,
            'Target-only leaf paths must be counted.',
        );
        self::assertSame(
            1,
            $panel->removed,
            'Baseline-only leaf paths must be counted.',
        );
        self::assertSame(
            1,
            $panel->changed,
            'Shared paths with different typed values must be counted.',
        );
        self::assertSame(
            2,
            $panel->unchanged,
            'Shared paths with equal typed values must be counted.',
        );
    }

    #[Test]
    public function fromSnapshotsFingerprintsFailureChangesWithoutRenderingFailureValues(): void
    {
        $summary = self::summary('failure');
        $baseline = new DebugSnapshot(
            $summary,
            [],
            ['request' => PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('baseline secret'))],
        );
        $target = new DebugSnapshot(
            $summary,
            [],
            ['request' => PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('target secret'))],
        );

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);
        $panel = null;

        foreach ($comparison->panels as $candidate) {
            $panel = $candidate;
            break;
        }

        self::assertInstanceOf(
            HistoryPanelComparison::class,
            $panel,
            'Failed panel comparison must be present.',
        );
        self::assertSame(
            'Failed',
            $panel->baselineState,
            'Baseline failure state must be retained without exposing its message.',
        );
        self::assertGreaterThan(
            0,
            $panel->differenceCount(),
            'Different failure envelopes must be detected through fingerprints.',
        );
    }

    #[Test]
    public function fromSnapshotsTreatsEqualPayloadsAsIdentical(): void
    {
        $summary = self::summary('same');
        $payload = ['request' => ['statusCode' => 200, 'nested' => []]];
        $snapshot = new DebugSnapshot($summary, $payload, []);

        $comparison = HistoryComparison::fromSnapshots($snapshot, $snapshot);

        self::assertFalse(
            $comparison->hasDifferences(),
            'Equal snapshots must remain identical.',
        );
        $panel = null;

        foreach ($comparison->panels as $candidate) {
            $panel = $candidate;
            break;
        }

        self::assertInstanceOf(
            HistoryPanelComparison::class,
            $panel,
            'Equal snapshots must retain their observed panel comparison.',
        );
        self::assertSame(
            0,
            $panel->differenceCount(),
            'Equal panel payloads must not report structural differences.',
        );
    }

    private static function summary(
        string $tag,
        float|null $processingTime = null,
        int|null $peakMemory = null,
        int $sqlCount = 0,
    ): RequestSummary {
        return new RequestSummary(
            tag: $tag,
            url: 'https://example.test/path',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: $sqlCount,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: $processingTime,
            peakMemory: $peakMemory,
        );
    }
}
