<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\storage\RequestSummary;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\HistoryRow;

use function date;

/**
 * Unit tests for {@see HistoryRow} covering the projection of a manifest {@see RequestSummary} into the row the
 * History GridView renders.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('history')]
final class HistoryRowTest extends TestCase
{
    public function testFromSummaryComputesTimeCompactWhenTimeIsPositive(): void
    {
        $row = HistoryRow::fromSummary(self::summary(['time' => 1_700_000_000.0]));

        self::assertSame(
            date('H:i:s', 1_700_000_000),
            $row->timeCompact,
            'A positive capture time must render as a clock string.',
        );
    }

    public function testFromSummaryLeavesTimeCompactEmptyWhenTimeIsZero(): void
    {
        self::assertSame(
            '',
            HistoryRow::fromSummary(self::summary(['time' => 0.0]))->timeCompact,
            'A zero capture time must render no clock string.',
        );
    }

    public function testFromSummaryPassesEveryFieldThroughUntouched(): void
    {
        $row = HistoryRow::fromSummary(
            self::summary(
                [
                    'tag' => 'tag-9',
                    'url' => 'https://example.test/orders',
                    'ajax' => true,
                    'method' => 'POST',
                    'ip' => '10.0.0.1',
                    'statusCode' => 404,
                    'sqlCount' => 7,
                    'excessiveCallersCount' => 2,
                    'mailCount' => 1,
                    'processingTime' => 0.125,
                    'peakMemory' => 1_048_576,
                ],
            ),
        );

        self::assertSame('tag-9', $row->tag, 'Tag must pass through.');
        self::assertSame('https://example.test/orders', $row->url, 'URL must pass through.');
        self::assertTrue($row->ajax, 'AJAX flag must pass through.');
        self::assertSame('POST', $row->method, 'Method must pass through.');
        self::assertSame('10.0.0.1', $row->ip, 'IP must pass through.');
        self::assertSame(404, $row->statusCode, 'Status code must pass through.');
        self::assertSame(7, $row->sqlCount, 'SQL count must pass through.');
        self::assertSame(2, $row->excessiveCallersCount, 'Excessive-caller count must pass through.');
        self::assertSame(1, $row->mailCount, 'Mail count must pass through.');
        self::assertSame(0.125, $row->processingTime, 'Processing time must pass through.');
        self::assertSame(1_048_576, $row->peakMemory, 'Peak memory must pass through.');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function summary(array $overrides = []): RequestSummary
    {
        return RequestSummary::fromArray(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 0,
                'mailFiles' => [],
                'processingTime' => null,
                'peakMemory' => null,
                ...$overrides,
            ],
        );
    }
}
