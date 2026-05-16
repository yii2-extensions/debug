<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\HistoryRow;

/**
 * Unit tests for {@see HistoryRow} covering the narrowing of the loose-typed manifest row into the typed view-model
 * consumed by the History GridView column closures.
 */
#[Group('panel')]
#[Group('history')]
final class HistoryRowTest extends TestCase
{
    public function testFromCoercesNumericStringsToTypedNumbers(): void
    {
        $row = HistoryRow::from(
            [
                'statusCode' => '200',
                'time' => '1700000000.5',
                'sqlCount' => '7',
                'peakMemory' => '12345',
            ],
        );

        self::assertSame(
            200,
            $row->statusCode,
            'Numeric string statusCode must coerce to int.',
        );
        self::assertSame(
            1_700_000_000.5,
            $row->time,
            'Numeric string time must coerce to float.',
        );
        self::assertSame(
            7,
            $row->sqlCount,
            'Numeric string sqlCount must coerce to int.',
        );
        self::assertSame(
            12345,
            $row->peakMemory,
            'Numeric string peakMemory must coerce to int.',
        );
    }

    public function testFromComputesTimeCompactWhenTimeIsPositive(): void
    {
        $row = HistoryRow::from(
            ['time' => 1_700_000_000],
        );

        self::assertMatchesRegularExpression(
            '/^\d{2}:\d{2}:\d{2}$/',
            $row->timeCompact,
            "Compact time must follow 'HH:MM:SS'.",
        );
    }

    public function testFromFallsBackToZeroOrEmptyOnMissingFields(): void
    {
        $row = HistoryRow::from(
            [],
        );

        self::assertSame(
            '',
            $row->tag,
            'Missing tag must default to empty string.',
        );
        self::assertSame(
            '',
            $row->method,
            'Missing method must default to empty string.',
        );
        self::assertSame(
            0,
            $row->statusCode,
            "Missing statusCode must default to '0'.",
        );
        self::assertSame(
            0.0,
            $row->time,
            "Missing time must default to '0.0'.",
        );
        self::assertSame(
            '',
            $row->timeCompact,
            'Missing time must yield empty compact display.',
        );
        self::assertNull(
            $row->processingTime,
            "Missing processingTime must default to 'null'.",
        );
        self::assertNull(
            $row->peakMemory,
            "Missing peakMemory must default to 'null'.",
        );
        self::assertFalse(
            $row->isAjax,
            "Missing ajax must default to 'false'.",
        );
    }

    public function testFromMarksAjaxFlagOnlyForTruthyValues(): void
    {
        self::assertTrue(
            HistoryRow::from(['ajax' => true])->isAjax,
            "Boolean true must mark ajax 'true'.",
        );
        self::assertTrue(
            HistoryRow::from(['ajax' => 1])->isAjax,
            "Numeric '1' must mark ajax 'true'.",
        );
        self::assertTrue(
            HistoryRow::from(['ajax' => '1'])->isAjax,
            "String '1' must mark ajax 'true'.",
        );
        self::assertFalse(
            HistoryRow::from(['ajax' => false])->isAjax,
            "Boolean false must mark ajax 'false'.",
        );
        self::assertFalse(
            HistoryRow::from(['ajax' => 0])->isAjax,
            "Numeric '0' must mark ajax 'false'.",
        );
        self::assertFalse(
            HistoryRow::from(['ajax' => '0'])->isAjax,
            "String '0' must mark ajax 'false'.",
        );
        self::assertFalse(
            HistoryRow::from(['ajax' => null])->isAjax,
            "Null must mark ajax 'false'.",
        );
    }

    public function testFromMixedAcceptsAlreadyTypedInstance(): void
    {
        $original = HistoryRow::from(
            ['tag' => 'abc', 'statusCode' => 200],
        );

        $passthrough = HistoryRow::fromMixed($original);

        self::assertSame(
            $original,
            $passthrough,
            'fromMixed must pass through already-typed instances.',
        );
    }

    public function testFromMixedFallsBackToEmptyArrayForNonArrayInput(): void
    {
        $row = HistoryRow::fromMixed(
            'not-an-array',
        );

        self::assertSame(
            '',
            $row->tag,
            'Non-array input must produce an empty row.',
        );
    }
}
