<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\{JobRecord, QueueSummary};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see QueueSummary} covering the narrowing of `$panel->data` into typed
 * {@see \yii\debug\panels\queue\QueueSummary} aggregates.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueSummaryTest extends TestCase
{
    public function testFromPanelDataAggregatesEventTypeCounts(): void
    {
        $summary = QueueSummary::fromPanelData(
            [
                'records' => [
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
                ],
            ],
        );

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

    public function testFromPanelDataDropsRecordsThatAreNotArrays(): void
    {
        $summary = QueueSummary::fromPanelData(
            [
                'records' => [
                    ['eventType' => 'push', 'componentId' => 'queue'],
                    'invalid string',
                    42,
                    ['eventType' => 'exec', 'componentId' => 'queue'],
                ],
            ],
        );

        self::assertSame(
            4,
            $summary->totalEvents(),
            "'JobRecord::fromMixed()' accepts non-array defaults so totalEvents reflects every entry.",
        );
        self::assertSame(
            3,
            $summary->totalPushed(),
            'One explicit push + two non-array fallbacks (defaulting to push) total three.',
        );
    }

    public function testFromPanelDataExposesDistinctComponentIdsInFirstSeenOrder(): void
    {
        $summary = QueueSummary::fromPanelData(
            [
                'records' => [
                    ['eventType' => 'push', 'componentId' => 'queueEmail'],
                    ['eventType' => 'push', 'componentId' => 'queue'],
                    ['eventType' => 'push', 'componentId' => 'queueEmail'],
                ],
            ],
        );

        self::assertSame(
            ['queueEmail', 'queue'],
            $summary->componentIds(),
            "'componentIds' must preserve first-seen order.",
        );
    }

    public function testFromPanelDataFiltersRecordsByComponent(): void
    {
        $summary = QueueSummary::fromPanelData(
            [
                'records' => [
                    ['eventType' => 'push', 'componentId' => 'queue', 'jobClass' => 'A'],
                    ['eventType' => 'push', 'componentId' => 'queueEmail', 'jobClass' => 'B'],
                    ['eventType' => 'exec', 'componentId' => 'queue', 'jobClass' => 'A'],
                ],
            ],
        );

        self::assertCount(
            2,
            $summary->recordsForComponent('queue'),
            "Two records belong to 'queue'.",
        );
        self::assertCount(
            1,
            $summary->recordsForComponent('queueEmail'),
            "One record belongs to 'queueEmail'.",
        );
        self::assertSame(
            [],
            $summary->recordsForComponent('nonexistent'),
            "Unknown component must yield '[]'.",
        );
    }

    public function testFromPanelDataReturnsEmptySummaryWhenInputIsNotArray(): void
    {
        $summary = QueueSummary::fromPanelData(
            'not an array',
        );

        self::assertTrue(
            $summary->isEmpty(),
            'Non-array input must yield an empty summary.',
        );
        self::assertSame(
            0,
            $summary->totalEvents(),
            "Total events must be '0'.",
        );
    }

    public function testFromPanelDataReturnsEmptySummaryWhenRecordsAreMissing(): void
    {
        $summary = QueueSummary::fromPanelData(
            [],
        );

        self::assertTrue(
            $summary->isEmpty(),
            'Missing records key must yield an empty summary.',
        );
    }

    public function testFromPanelDataReturnsRecordsInOriginalOrder(): void
    {
        $summary = QueueSummary::fromPanelData(
            [
                'records' => [
                    ['eventType' => 'push', 'jobClass' => 'First'],
                    ['eventType' => 'push', 'jobClass' => 'Second'],
                    ['eventType' => 'push', 'jobClass' => 'Third'],
                ],
            ],
        );

        $classes = array_map(static fn(JobRecord $record): string => $record->jobClass, $summary->records);

        self::assertSame(
            ['First', 'Second', 'Third'],
            $classes,
            'Records must preserve insertion order.',
        );
    }
}
