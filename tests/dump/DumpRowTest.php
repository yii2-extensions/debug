<?php

declare(strict_types=1);

namespace yii\debug\tests\dump;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\dump\DumpRow;
use yii\debug\storage\HydrationException;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see DumpRow} covering the capture-time narrowing of Yii logger tuples and the strict JSON hydration
 * that restores them without coercion.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('dump')]
final class DumpRowTest extends TestCase
{
    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new DumpRow(
            message: '&lt;?php "hello"',
            level: Logger::LEVEL_TRACE,
            category: 'application',
            time: 1_700_000_000_500.0,
            trace: [['file' => '/app/index.php', 'line' => 12]],
        );

        self::assertEquals(
            $row,
            DumpRow::fromArray($row->jsonSerialize(), '$.panels.dump.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromLoggerTupleFallsBackToEmptyValuesForAMalformedTuple(): void
    {
        $row = DumpRow::fromLoggerTuple([]);

        self::assertSame(
            '',
            $row->message,
            'Missing payload must fall back to an empty string.',
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
            [],
            $row->trace,
            'Missing trace must fall back to an empty list.',
        );
    }

    public function testFromLoggerTupleKeepsOnlyStringKeyedArrayTraceFrames(): void
    {
        $row = DumpRow::fromLoggerTuple(
            [
                'msg',
                Logger::LEVEL_TRACE,
                'app',
                1.0,
                [['file' => 'a.php', 7 => 'dropped'], 'not-a-frame'],
            ],
        );

        self::assertSame(
            [['file' => 'a.php']],
            $row->trace,
            'Non-array frames and integer keys must be dropped.',
        );
    }

    public function testFromLoggerTupleNarrowsNumericStringsAndScalesTime(): void
    {
        $row = DumpRow::fromLoggerTuple(
            [
                'msg',
                '8',
                'app',
                '2.5',
                [],
            ],
        );

        self::assertSame(
            'msg',
            $row->message,
            'Payload must round-trip verbatim.',
        );
        self::assertSame(
            8,
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
    }

    public function testThrowHydrationExceptionWhenAFieldIsMissing(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.dump.entries[0].trace': expected a required field.",
        );

        DumpRow::fromArray(
            [
                'message' => 'msg',
                'level' => Logger::LEVEL_TRACE,
                'category' => 'app',
                'time' => 1.0,
            ],
            '$.panels.dump.entries[0]',
        );
    }

    public function testThrowHydrationExceptionWhenTimeIsANumericString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.dump.entries[0].time': expected a number.",
        );

        DumpRow::fromArray(
            [
                'message' => 'msg',
                'level' => Logger::LEVEL_TRACE,
                'category' => 'app',
                'time' => '2500',
                'trace' => [],
            ],
            '$.panels.dump.entries[0]',
        );
    }
}
