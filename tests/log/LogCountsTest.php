<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\log\{LogCounts, LogRow};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see LogCounts} covering the level totals derived from the captured rows.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('log')]
final class LogCountsTest extends TestCase
{
    public function testFromRowsAggregatesLevelsCorrectly(): void
    {
        $counts = LogCounts::fromRows(
            [
                self::row(Logger::LEVEL_INFO),
                self::row(Logger::LEVEL_ERROR),
                self::row(Logger::LEVEL_WARNING),
                self::row(Logger::LEVEL_ERROR),
                self::row(Logger::LEVEL_TRACE),
            ],
        );

        self::assertSame(5, $counts->total, 'Total must span every level.');
        self::assertSame(2, $counts->errors, 'Two rows are at error level.');
        self::assertSame(1, $counts->warnings, 'One row is at warning level.');
        self::assertSame(1, $counts->info, 'One row is at info level.');
    }

    public function testFromRowsExposesHasFlagsForNonZeroCounts(): void
    {
        $counts = LogCounts::fromRows([self::row(Logger::LEVEL_ERROR)]);

        self::assertTrue($counts->hasErrors(), 'A captured error must raise the flag.');
        self::assertFalse($counts->hasWarnings(), 'No warning was captured.');
        self::assertFalse($counts->hasInfo(), 'No info entry was captured.');
    }

    public function testFromRowsReturnsAllZeroCountsWhenNoRowsWereCaptured(): void
    {
        $counts = LogCounts::fromRows([]);

        self::assertSame(0, $counts->total, 'Empty capture must total zero.');
        self::assertFalse($counts->hasErrors(), 'Empty capture must report no errors.');
        self::assertFalse($counts->hasWarnings(), 'Empty capture must report no warnings.');
        self::assertFalse($counts->hasInfo(), 'Empty capture must report no info entries.');
    }

    private static function row(int $level): LogRow
    {
        return new LogRow(
            id: 1,
            message: 'message',
            level: $level,
            category: 'app',
            time: 1_000.0,
            timeOfPrevious: 1_000.0,
            timeSincePrevious: 0.0,
            idOfPrevious: null,
            idOfNext: null,
            memory: 0,
            trace: [],
        );
    }
}
