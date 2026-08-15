<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\log\LogRow;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see LogRow} covering the capture-time narrowing of Yii logger tuples and the strict JSON hydration
 * that restores them without coercion.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('log')]
final class LogRowTest extends TestCase
{
    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new LogRow(
            id: 7,
            message: 'boom',
            level: Logger::LEVEL_ERROR,
            category: 'yii\\db\\Command::query',
            time: 1_700_000_000_500.0,
            timeOfPrevious: 1_700_000_000_000.0,
            timeSincePrevious: 0.5,
            idOfPrevious: 6,
            idOfNext: 8,
            memory: 2_048,
            trace: [['file' => '/app/index.php', 'line' => 12]],
        );

        self::assertEquals(
            $row,
            LogRow::fromArray($row->jsonSerialize(), '$.panels.log.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromLoggerTupleExportsNonStringMessage(): void
    {
        $row = LogRow::fromLoggerTuple(
            [
                ['a' => 1],
                Logger::LEVEL_INFO,
                'app',
                1.0,
                [],
            ],
            1,
            1.0,
            null,
            null,
        );

        self::assertStringContainsString(
            'a',
            $row->message,
            'Array payload must be exported to a readable string.',
        );
    }

    public function testFromLoggerTupleFallsBackToZeroesForAMalformedTuple(): void
    {
        $row = LogRow::fromLoggerTuple(
            [],
            1,
            0.0,
            null,
            null,
        );

        self::assertSame(
            0,
            $row->level,
            "Missing level must fall back to '0'.",
        );
        self::assertSame(
            '',
            $row->category,
            'Missing category must fall back to an empty string.',
        );
        self::assertSame(
            0.0,
            $row->time,
            "Missing timestamp must fall back to '0'.",
        );
        self::assertSame(
            0,
            $row->memory,
            "Missing memory must fall back to '0'.",
        );
        self::assertSame(
            [],
            $row->trace,
            'Missing trace must fall back to an empty list.',
        );
    }

    public function testFromLoggerTupleKeepsOnlyStringKeyedArrayTraceFrames(): void
    {
        $row = LogRow::fromLoggerTuple(
            [
                'msg',
                Logger::LEVEL_INFO,
                'app',
                1.0,
                [
                    [
                        'file' => 'a.php',
                        7 => 'dropped',
                    ],
                    'not-a-frame',
                ],
            ],
            1,
            1.0,
            null,
            null,
        );

        self::assertSame(
            [['file' => 'a.php']],
            $row->trace,
            'Non-array frames and integer keys must be dropped.',
        );
    }

    public function testFromLoggerTupleNarrowsNumericStringsAndScalesTime(): void
    {
        $row = LogRow::fromLoggerTuple(
            [
                'msg',
                '4',
                'app',
                '2.5',
                [],
                '4096',
            ],
            3,
            1.5,
            2,
            4,
        );

        self::assertSame(
            3,
            $row->id,
            'Row id is assigned by the caller.',
        );
        self::assertSame(
            4,
            $row->level,
            "Numeric-string level must narrow to 'int'.",
        );
        self::assertSame(
            'app',
            $row->category,
            'Category must come from the logger tuple category position.',
        );
        self::assertSame(
            2_500.0,
            $row->time,
            'Timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            1_500.0,
            $row->timeOfPrevious,
            'Previous timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            1.0,
            $row->timeSincePrevious,
            'Delta stays in seconds.',
        );
        self::assertSame(
            4_096,
            $row->memory,
            "Numeric-string memory must narrow to 'int'.",
        );
        self::assertSame(
            2,
            $row->idOfPrevious,
            'Previous id is assigned by the caller.',
        );
        self::assertSame(
            4,
            $row->idOfNext,
            'Next id is assigned by the caller.',
        );
    }

    public function testThrowHydrationExceptionWhenLevelIsANumericString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.log.entries[0].level': expected an integer.",
        );

        LogRow::fromArray(
            self::payload(['level' => '4']),
            '$.panels.log.entries[0]',
        );
    }

    public function testThrowHydrationExceptionWhenTraceIsNotAListOfObjects(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.log.entries[0].trace[0]': expected an object.",
        );

        LogRow::fromArray(
            self::payload(['trace' => ['not-a-frame']]),
            '$.panels.log.entries[0]',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payload(array $overrides = []): array
    {
        return [
            'id' => 1,
            'message' => 'msg',
            'level' => Logger::LEVEL_INFO,
            'category' => 'app',
            'time' => 1.0,
            'timeOfPrevious' => 1.0,
            'timeSincePrevious' => 0.0,
            'idOfPrevious' => null,
            'idOfNext' => null,
            'memory' => 0,
            'trace' => [],
            ...$overrides,
        ];
    }
}
