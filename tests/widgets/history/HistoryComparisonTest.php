<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\history;

use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use RuntimeException;
use yii\debug\tests\provider\{HistoryComparisonProvider, SummaryMetricComparisonProvider};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\{HistoryComparison, HistoryMetricComparison, HistoryPanelComparison};

/**
 * Unit tests for {@see HistoryComparison} metric deltas, panel ordering, and privacy-preserving fingerprints.
 *
 * {@see HistoryComparisonProvider} for test case data providers.
 */
#[Group('history')]
final class HistoryComparisonTest extends TestCase
{
    public function testDifferenceCountSumsAddedRemovedAndChangedCounters(): void
    {
        $panel = new HistoryPanelComparison(
            id: 'request',
            label: 'Request',
            baselineState: 'Captured',
            targetState: 'Captured',
            added: 2,
            removed: 1,
            changed: 0,
            unchanged: 5,
        );

        self::assertSame(
            3,
            $panel->differenceCount(),
            'Sum must be `added + removed + changed`.',
        );
        self::assertSame(
            'request',
            $panel->id,
            'Panel identity must round-trip.',
        );
        self::assertSame(
            5,
            $panel->unchanged,
            'Unchanged counter must be carried.',
        );
    }

    public function testFromSnapshotsComputesDownwardTrendWithSignedPercentage(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline', processingTime: 0.015), [], []);
        $target = new DebugSnapshot($this->summary('target', processingTime: 0.01), [], []);

        $duration = self::metric(
            HistoryComparison::fromSnapshots($baseline, $target),
            'Duration',
        );

        self::assertSame(
            'down',
            $duration->trend,
            'Decreasing values must trend down.',
        );
        self::assertSame(
            '-5.00 ms (-33.3%)',
            $duration->delta,
            'Negative delta must omit the plus sign.',
        );
    }

    public function testFromSnapshotsComputesMetricAndPanelDifferencesWithoutExposingValues(): void
    {
        $baseline = new DebugSnapshot(
            $this->summary(
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
            $this->summary(
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

        $duration = self::metric($comparison, 'Duration');
        $sqlQueries = self::metric($comparison, 'SQL queries');

        self::assertSame(
            '+5.00 ms (+50.0%)',
            $duration->delta,
            'Duration must expose the absolute and relative target delta.',
        );
        self::assertSame(
            '+3 (+150.0%)',
            $sqlQueries->delta,
            'Integer metrics must expose the absolute and relative target delta.',
        );

        $panel = self::firstPanel($comparison);

        self::assertSame(
            'request',
            $panel->id,
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

    public function testFromSnapshotsCountsMultipleChangedLeaves(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline'), ['p' => ['a' => 1, 'b' => 2]], []);
        $target = new DebugSnapshot($this->summary('target'), ['p' => ['a' => 9, 'b' => 8]], []);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            2,
            $panel->changed,
            'Every changed shared leaf must count.',
        );
    }

    public function testFromSnapshotsCountsRenamedAndChangedFailureLeavesWithoutForcingChanged(): void
    {
        $failure = PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('boom'));

        $envelope = $failure->jsonSerialize();

        self::assertArrayHasKey(
            'stage',
            $envelope,
            'Failure envelope must expose its stage.',
        );
        self::assertArrayHasKey(
            'exception',
            $envelope,
            'Failure envelope must expose its exception.',
        );
        self::assertIsArray(
            $envelope['exception'],
            'Exception envelope must be an array.',
        );

        $envelope['stagex'] = $envelope['stage'];
        $envelope['exception']['message'] = 'other message';
        $envelope['exception']['code'] = 99;

        unset($envelope['stage']);

        $baseline = new DebugSnapshot($this->summary('baseline'), ['x' => ['failure' => $envelope]], []);
        $target = new DebugSnapshot($this->summary('target'), [], ['x' => $failure]);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            1,
            $panel->added,
            'Renamed key must add exactly one leaf.',
        );
        self::assertSame(
            1,
            $panel->removed,
            'Renamed key must remove exactly one leaf.',
        );
        self::assertSame(
            2,
            $panel->changed,
            'Changed leaves must keep their exact count.',
        );
    }

    public function testFromSnapshotsCountsRenamedFailureKeyWithoutForcingChanged(): void
    {
        $failure = PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('boom'));

        $envelope = $failure->jsonSerialize();

        self::assertArrayHasKey(
            'stage',
            $envelope,
            'Failure envelope must expose its stage.',
        );

        $envelope['stagex'] = $envelope['stage'];

        unset($envelope['stage']);

        $baseline = new DebugSnapshot($this->summary('baseline'), ['x' => ['failure' => $envelope]], []);
        $target = new DebugSnapshot($this->summary('target'), [], ['x' => $failure]);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            'Captured',
            $panel->baselineState,
            'Payload side must read `Captured`.',
        );
        self::assertSame(
            'Failed',
            $panel->targetState,
            'Failure side must read `Failed`.',
        );
        self::assertSame(
            1,
            $panel->added,
            'Renamed key must add exactly one leaf.',
        );
        self::assertSame(
            1,
            $panel->removed,
            'Renamed key must remove exactly one leaf.',
        );
        self::assertSame(
            0,
            $panel->changed,
            'Non-cancelling counters must not force a change.',
        );
    }

    public function testFromSnapshotsCountsSharedLeavesAfterBaselineOnlyPath(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline'), ['p' => ['only' => 1, 'shared' => 2]], []);
        $target = new DebugSnapshot($this->summary('target'), ['p' => ['shared' => 2]], []);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            1,
            $panel->removed,
            'Baseline-only leaf must count as removed.',
        );
        self::assertSame(
            1,
            $panel->unchanged,
            'Shared equal leaf after a removed one must still count.',
        );
    }

    public function testFromSnapshotsDeduplicatesPanelSeenInPayloadAndFailure(): void
    {
        $failure = PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('boom'));

        $baseline = new DebugSnapshot($this->summary('baseline'), ['dump' => ['v' => 1]], ['dump' => $failure]);
        $target = new DebugSnapshot($this->summary('target'), [], []);

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);

        self::assertCount(
            1,
            $comparison->panels,
            'One comparison per observed panel ID.',
        );
    }

    public function testFromSnapshotsDistinguishesAbsentEmptyAndTypedPayloads(): void
    {
        $baseline = new DebugSnapshot(
            RequestSummary::create('baseline'),
            ['empty' => [], 'typed' => ['null' => null, 'false' => false, 'zero' => 0, 'list' => []]],
            [],
        );
        $target = new DebugSnapshot(
            RequestSummary::create('target'),
            ['typed' => ['null' => false, 'false' => 0, 'zero' => 0.0, 'list' => null], 'added' => []],
            [],
        );

        $beforeBaseline = $baseline->jsonSerialize();
        $beforeTarget = $target->jsonSerialize();

        $comparison = HistoryComparison::fromSnapshots($baseline, $target, ['typed' => 'Typed']);

        $counts = [];

        foreach ($comparison->panels as $panel) {
            $counts[$panel->id] = [$panel->added, $panel->removed, $panel->changed, $panel->unchanged];
        }

        self::assertSame(
            ['typed' => [0, 0, 4, 0], 'added' => [1, 0, 0, 0], 'empty' => [0, 1, 0, 0]],
            $counts,
            'Shared comparison must preserve type distinctions, empty captures, and adapter ordering.',
        );
        self::assertSame(
            $beforeBaseline,
            $baseline->jsonSerialize(),
            'The baseline diagnostic values must remain intact.',
        );
        self::assertSame(
            $beforeTarget,
            $target->jsonSerialize(),
            'The target diagnostic values must remain intact.',
        );
    }

    public function testFromSnapshotsDistinguishesIntegerAndFloatLeaves(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline'), ['p' => ['value' => 1]], []);
        $target = new DebugSnapshot($this->summary('target'), ['p' => ['value' => 1.0]], []);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            1,
            $panel->changed,
            'JSON-equivalent scalars with different types must count as changed.',
        );
    }

    public function testFromSnapshotsFingerprintsFailureChangesWithoutRenderingFailureValues(): void
    {
        $summary = $this->summary('failure');

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

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

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

    public function testFromSnapshotsFormatsScaledMetricValues(): void
    {
        $baseline = new DebugSnapshot(
            $this->summary('baseline', processingTime: 0.01, peakMemory: 10_485_760_000_000),
            [],
            [],
        );
        $target = new DebugSnapshot(
            $this->summary('target', processingTime: 0.015, peakMemory: 10_485_760_000_000),
            [],
            [],
        );

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);

        $duration = self::metric($comparison, 'Duration');
        $memory = self::metric($comparison, 'Peak memory');

        self::assertSame(
            '10.00 ms',
            $duration->baseline,
            'Seconds must scale to milliseconds.',
        );
        self::assertSame(
            '15.00 ms',
            $duration->target,
            'Seconds must scale to milliseconds.',
        );
        self::assertSame(
            'up',
            $duration->trend,
            'Increasing values must trend up.',
        );
        self::assertSame(
            '10,000,000.00 MB',
            $memory->baseline,
            'Bytes must scale to mebibytes.',
        );
        self::assertSame(
            'No change',
            $memory->delta,
            'Equal values must read `No change`.',
        );
        self::assertSame(
            'neutral',
            $memory->trend,
            'Equal values must keep a neutral trend.',
        );
    }

    /**
     * @param array<string, mixed> $baselinePayload
     * @param array<string, mixed> $targetPayload
     */
    #[DataProviderExternal(HistoryComparisonProvider::class, 'distinctEscapedPaths')]
    public function testFromSnapshotsKeepsEscapedLeafPathsDistinct(array $baselinePayload, array $targetPayload): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline'), ['p' => $baselinePayload], []);
        $target = new DebugSnapshot($this->summary('target'), ['p' => $targetPayload], []);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            1,
            $panel->added,
            'Escaped target path must stay distinct.',
        );
        self::assertSame(
            1,
            $panel->removed,
            'Escaped baseline path must stay distinct.',
        );
        self::assertSame(
            0,
            $panel->unchanged,
            'No shared paths must remain.',
        );
    }

    public function testFromSnapshotsMarksCapturedAndMissingPanelStates(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline'), ['db' => ['queries' => 2]], []);
        $target = new DebugSnapshot($this->summary('target'), [], []);

        $panel = self::firstPanel(HistoryComparison::fromSnapshots($baseline, $target));

        self::assertSame(
            'Captured',
            $panel->baselineState,
            'Payload presence must read `Captured`.',
        );
        self::assertSame(
            'Not captured',
            $panel->targetState,
            'Absent payload must read `Not captured`.',
        );
        self::assertSame(
            1,
            $panel->removed,
            'Baseline-only leaves must count as removed.',
        );
    }

    public function testFromSnapshotsMarksMissingDurationSidesAsNotComparable(): void
    {
        $captured = $this->summary('captured', processingTime: 2.5);
        $missing = $this->summary('missing');

        $baselineMissing = self::metric(
            HistoryComparison::fromSnapshots(
                new DebugSnapshot($missing, [], []),
                new DebugSnapshot($captured, [], []),
            ),
            'Duration',
        );

        self::assertSame(
            'Not captured',
            $baselineMissing->baseline,
            'Missing side must read `Not captured`.',
        );
        self::assertSame(
            '2,500.00 ms',
            $baselineMissing->target,
            'Captured side must scale and format.',
        );
        self::assertSame(
            'Not comparable',
            $baselineMissing->delta,
            'Mixed capture must not be comparable.',
        );
        self::assertSame(
            'neutral',
            $baselineMissing->trend,
            'Mixed capture must keep a neutral trend.',
        );

        $targetMissing = self::metric(
            HistoryComparison::fromSnapshots(
                new DebugSnapshot($captured, [], []),
                new DebugSnapshot($missing, [], []),
            ),
            'Duration',
        );

        self::assertSame(
            '2,500.00 ms',
            $targetMissing->baseline,
            'Captured side must scale and format.',
        );
        self::assertSame(
            'Not captured',
            $targetMissing->target,
            'Missing side must read `Not captured`.',
        );
    }

    public function testFromSnapshotsMarksStateOnlyTransitionAsChanged(): void
    {
        $failure = PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('boom'));

        $baseline = new DebugSnapshot(
            $this->summary('baseline'),
            ['request' => ['failure' => $failure->jsonSerialize()]],
            [],
        );
        $target = new DebugSnapshot($this->summary('target'), [], ['request' => $failure]);

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);

        $panel = self::firstPanel($comparison);

        self::assertSame(
            0,
            $panel->added,
            'Identical failure leaves must not count as added.',
        );
        self::assertSame(
            0,
            $panel->removed,
            'Identical failure leaves must not count as removed.',
        );
        self::assertSame(
            1,
            $panel->changed,
            'A state-only transition must normalize to one change.',
        );
        self::assertTrue(
            $comparison->hasDifferences(),
            'The normalized state transition must flag the comparison.',
        );
    }

    public function testFromSnapshotsOmitsPercentageForZeroBaseline(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline', processingTime: 0.0), [], []);
        $target = new DebugSnapshot($this->summary('target', processingTime: 0.002, sqlCount: 3), [], []);

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);

        self::assertSame(
            '+2.00 ms',
            self::metric($comparison, 'Duration')->delta,
            'Float zero baseline must omit the percentage.',
        );
        self::assertSame(
            '+3',
            self::metric($comparison, 'SQL queries')->delta,
            'Integer zero baseline must omit the percentage.',
        );
    }

    public function testFromSnapshotsOrdersLabeledPanelsFirstThenExtrasAlphabetically(): void
    {
        $payload = ['zeta' => ['v' => 1], 'request' => ['v' => 1], 'db' => ['v' => 1], 'alpha' => ['v' => 1]];
        $snapshot = new DebugSnapshot($this->summary('tag'), $payload, []);

        $comparison = HistoryComparison::fromSnapshots(
            $snapshot,
            $snapshot,
            ['request' => 'Request', 'db' => 'Database', 'log' => 'Logs'],
        );

        $ids = array_map(static fn(HistoryPanelComparison $panel): string => $panel->id, $comparison->panels);

        self::assertSame(
            ['request', 'db', 'alpha', 'zeta'],
            $ids,
            'Order: label order, then sorted extras.',
        );
    }

    /**
     * @param list<array{string, string, string, string, string, string|null}> $expected
     */
    #[DataProviderExternal(SummaryMetricComparisonProvider::class, 'summaries')]
    public function testFromSnapshotsPreservesSummaryMetricContracts(
        RequestSummary $baseline,
        RequestSummary $target,
        array $expected,
    ): void {
        $baselineSnapshot = new DebugSnapshot($baseline, [], []);
        $targetSnapshot = new DebugSnapshot($target, [], []);
        $comparison = HistoryComparison::fromSnapshots($baselineSnapshot, $targetSnapshot);
        $actual = [];

        foreach ($comparison->metrics as $metric) {
            $actual[] = [
                $metric->label,
                $metric->baseline,
                $metric->target,
                $metric->delta,
                $metric->trend,
                $metric->panelId,
            ];
        }

        self::assertSame(
            $expected,
            $actual,
            'All summary metric fields and their order must remain exact.',
        );
        self::assertSame(
            $baselineSnapshot,
            $comparison->baseline,
            'The original baseline snapshot must be retained.',
        );
        self::assertSame(
            $targetSnapshot,
            $comparison->target,
            'The original target snapshot must be retained.',
        );
    }

    public function testFromSnapshotsReportsStatusAndAjaxMetrics(): void
    {
        $baseline = new DebugSnapshot($this->summary('baseline', statusCode: 0, ajax: true), [], []);
        $target = new DebugSnapshot($this->summary('target', statusCode: 500), [], []);

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);

        $status = self::metric($comparison, 'Status');
        $ajax = self::metric($comparison, 'AJAX');

        self::assertSame(
            'Not captured',
            $status->baseline,
            'Zero status must read `Not captured`.',
        );
        self::assertSame(
            '500',
            $status->target,
            'Non-zero status must render verbatim.',
        );
        self::assertSame(
            'Changed',
            $status->delta,
            'Different text values must read `Changed`.',
        );
        self::assertSame(
            'Yes',
            $ajax->baseline,
            '`true` must map to `Yes`.',
        );
        self::assertSame(
            'No',
            $ajax->target,
            '`false` must map to `No`.',
        );
    }

    public function testFromSnapshotsTreatsEqualPayloadsAsIdentical(): void
    {
        $summary = $this->summary('same');

        $payload = [
            'request' => [
                'statusCode' => 200,
                'nested' => [],
            ],
        ];

        $snapshot = new DebugSnapshot($summary, $payload, []);

        $comparison = HistoryComparison::fromSnapshots($snapshot, $snapshot);

        self::assertFalse(
            $comparison->hasDifferences(),
            'Equal snapshots must remain identical.',
        );
        $panel = self::firstPanel($comparison);

        self::assertSame(
            0,
            $panel->differenceCount(),
            'Equal panel payloads must not report structural differences.',
        );
    }

    public function testHasDifferencesDetectsMetricOnlyChange(): void
    {
        $payload = ['request' => ['a' => 1]];

        $baseline = new DebugSnapshot($this->summary('baseline', sqlCount: 1), $payload, []);
        $target = new DebugSnapshot($this->summary('target', sqlCount: 4), $payload, []);

        $comparison = HistoryComparison::fromSnapshots($baseline, $target);

        self::assertTrue(
            $comparison->hasDifferences(),
            'A metric-only delta must flag the comparison.',
        );
    }

    private static function firstPanel(HistoryComparison $comparison): HistoryPanelComparison
    {
        self::assertNotEmpty(
            $comparison->panels,
            'At least one panel comparison must exist.',
        );

        return $comparison->panels[0];
    }

    private static function metric(HistoryComparison $comparison, string $label): HistoryMetricComparison
    {
        foreach ($comparison->metrics as $metric) {
            if ($metric->label === $label) {
                return $metric;
            }
        }

        self::fail(
            "Metric '{$label}' is missing from the comparison.",
        );
    }

    private function summary(
        string $tag,
        float|null $processingTime = null,
        int|null $peakMemory = null,
        int $sqlCount = 0,
        int $statusCode = 200,
        bool $ajax = false,
    ): RequestSummary {
        return $this->requestSummary(
            $tag,
            [
                'ajax' => $ajax,
                'peakMemory' => $peakMemory,
                'processingTime' => $processingTime,
                'sqlCount' => $sqlCount,
                'statusCode' => $statusCode,
            ],
        );
    }
}
