<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\profile\ProfileRow;
use yii\debug\storage\HydrationException;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ProfileRow} covering the capture-time narrowing of logger timings and the strict JSON hydration
 * that restores them without coercion.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileRowTest extends TestCase
{
    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new ProfileRow(
            timestamp: 1_700_000_000_000.0,
            duration: 12.5,
            category: 'yii\\db\\Command::query',
            info: 'SELECT *',
            level: 2,
            seq: 3,
            memory: 1_572_864,
            memoryDiff: 1_048_576,
            trace: [['file' => '/app/index.php', 'line' => 12]],
        );

        self::assertEquals(
            $row,
            ProfileRow::fromArray($row->jsonSerialize(), '$.panels.profiling.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromTimingClampsNegativeLevelToZero(): void
    {
        $row = ProfileRow::fromTiming(['timestamp' => 1.0, 'duration' => 0.5, 'level' => -3], 0);

        self::assertNotNull($row, 'A timing with timestamp and duration must produce a row.');
        self::assertSame(0, $row->level, 'A negative level must clamp to `0`.');
    }

    public function testFromTimingNarrowsNumericStringsAndScalesToMilliseconds(): void
    {
        $row = ProfileRow::fromTiming(
            ['timestamp' => '2.5', 'duration' => '0.125', 'level' => '2', 'memory' => '2048', 'memoryDiff' => '512'],
            7,
        );

        self::assertNotNull($row, 'A timing with timestamp and duration must produce a row.');
        self::assertSame(2_500.0, $row->timestamp, 'Timestamp must be scaled to milliseconds.');
        self::assertSame(125.0, $row->duration, 'Duration must be scaled to milliseconds.');
        self::assertSame(2, $row->level, 'Numeric-string level must narrow to `int`.');
        self::assertSame(2_048, $row->memory, 'Numeric-string memory must narrow to `int`.');
        self::assertSame(512, $row->memoryDiff, 'Numeric-string memory delta must narrow to `int`.');
        self::assertSame(7, $row->seq, 'Sequence index is assigned by the caller.');
    }

    public function testFromTimingReturnsNullForIncompleteTimings(): void
    {
        self::assertNull(ProfileRow::fromTiming('not-an-array', 0), 'A non-array timing yields `null`.');
        self::assertNull(ProfileRow::fromTiming(['duration' => 1.0], 0), 'A missing timestamp yields `null`.');
        self::assertNull(ProfileRow::fromTiming(['timestamp' => 1.0], 0), 'A missing duration yields `null`.');
    }

    public function testMaxDurationReturnsTheLongestBlock(): void
    {
        self::assertSame(0.0, ProfileRow::maxDuration([]), 'An empty capture has no maximum.');
        self::assertSame(
            12.5,
            ProfileRow::maxDuration([self::row(1.0), self::row(12.5), self::row(3.0)]),
            'The longest block wins.',
        );
    }

    public function testThrowHydrationExceptionWhenDurationIsANumericString(): void
    {
        $this->expectException(HydrationException::class);

        ProfileRow::fromArray(
            [
                'timestamp' => 1.0,
                'duration' => '12.5',
                'category' => 'app',
                'info' => '',
                'level' => 0,
                'seq' => 0,
                'memory' => 0,
                'memoryDiff' => 0,
                'trace' => [],
            ],
            '$.panels.profiling.entries[0]',
        );
    }

    private static function row(float $duration): ProfileRow
    {
        return new ProfileRow(0.0, $duration, 'app', '', 0, 0, 0, 0, []);
    }
}
