<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\{JobRecord, QueueSnapshot, QueueSummary};
use yii\debug\tests\support\TestCase;

use function is_array;

/**
 * Unit tests for {@see QueueSummary} covering the narrowing of captured records into typed
 * {@see \yii\debug\panels\queue\QueueSummary} aggregates.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueSummaryTest extends TestCase
{
    public function testCaptureDropsRecordsThatAreNotArrays(): void
    {
        $summary = QueueSummary::fromRecords(
            QueueSnapshot::capture(
                [
                    ['eventType' => 'push', 'componentId' => 'queue'],
                    'invalid string',
                    42,
                    ['eventType' => 'exec', 'componentId' => 'queue'],
                ],
            )->entries(),
        );

        self::assertSame(2, $summary->totalEvents(), 'Non-array entries must be dropped at capture.');
        self::assertSame(1, $summary->totalPushed(), 'Only the explicit push survives.');
    }
    public function testFromRecordsAggregatesEventTypeCounts(): void
    {
        $summary = QueueSummary::fromRecords(self::records([
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'jobClass' => 'A',
            ],
            [
                'eventType' => 'push',
                'componentId' => 'queue',
                'jobClass' => 'A',
            ],
            [
                'eventType' => 'exec',
                'componentId' => 'queue',
                'jobClass' => 'A',
            ],
            [
                'eventType' => 'error',
                'componentId' => 'queue',
                'jobClass' => 'B',
            ],
        ]));

        self::assertSame(
            4,
            $summary->totalEvents(),
            'Total events must reflect every record.',
        );
        self::assertSame(
            2,
            $summary->totalPushed(),
            "Pushed count must reflect 'push' records.",
        );
        self::assertSame(
            1,
            $summary->totalExecuted(),
            "Executed count must reflect 'exec' records.",
        );
        self::assertSame(
            1,
            $summary->totalErrors(),
            "Errors count must reflect 'error' records.",
        );
        self::assertTrue(
            $summary->hasErrors(),
            "'hasErrors' must reflect a non-zero error count.",
        );
    }

    public function testFromRecordsExposesDistinctComponentIdsInFirstSeenOrder(): void
    {
        $summary = QueueSummary::fromRecords(self::records([
            ['eventType' => 'push', 'componentId' => 'queueEmail'],
            ['eventType' => 'push', 'componentId' => 'queue'],
            ['eventType' => 'push', 'componentId' => 'queueEmail'],
        ]));

        self::assertSame(
            ['queueEmail', 'queue'],
            $summary->componentIds(),
            "'componentIds' must preserve first-seen order.",
        );
    }

    public function testFromRecordsReturnsEmptySummaryForAnEmptyList(): void
    {
        $summary = QueueSummary::fromRecords([]);

        self::assertTrue(
            $summary->isEmpty(),
            'An empty list must yield an empty summary.',
        );
        self::assertSame(
            0,
            $summary->totalEvents(),
            "Total events must be '0'.",
        );
    }


    public function testFromRecordsReturnsRecordsInOriginalOrder(): void
    {
        $summary = QueueSummary::fromRecords(self::records([
            ['eventType' => 'push', 'jobClass' => 'First'],
            ['eventType' => 'push', 'jobClass' => 'Second'],
            ['eventType' => 'push', 'jobClass' => 'Third'],
        ]));

        $classes = array_map(static fn(JobRecord $record): string => $record->jobClass, $summary->records);

        self::assertSame(
            ['First', 'Second', 'Third'],
            $classes,
            'Records must preserve insertion order.',
        );
    }

    /**
     * @param array<int, mixed> $rows
     *
     * @return list<JobRecord>
     */
    private static function records(array $rows): array
    {
        $records = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $records[] = JobRecord::fromCapture($row);
            }
        }

        return $records;
    }
}
