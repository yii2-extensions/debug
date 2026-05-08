<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\db\QueryRowNormalizer;

/**
 * Unit tests for {@see QueryRowNormalizer} covering the narrowing of GridView callback arguments into a typed
 * {@see \yii\debug\panels\db\QueryRow}.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('db')]
final class QueryRowNormalizerTest extends TestCase
{
    public function testFromClampsDuplicateToMinimumOfOne(): void
    {
        $zero = QueryRowNormalizer::from(
            ['duplicate' => 0],
        );
        $negative = QueryRowNormalizer::from(
            ['duplicate' => -5],
        );
        $missing = QueryRowNormalizer::from(
            [],
        );

        self::assertSame(
            1,
            $zero->duplicate,
            "A '0' duplicate must clamp to '1'.",
        );
        self::assertSame(
            1,
            $negative->duplicate,
            "A negative duplicate must clamp to '1'.",
        );
        self::assertSame(
            1,
            $missing->duplicate,
            "A missing duplicate must default to '1'.",
        );
    }

    public function testFromCoercesNumericStringsToFloatAndInt(): void
    {
        $row = QueryRowNormalizer::from(
            [
                'duration' => '12.5',
                'timestamp' => '1700000000.123',
                'seq' => '4',
                'duplicate' => '2',
                'rows' => '99',
            ],
        );

        self::assertSame(
            12.5,
            $row->duration,
            'Numeric string must coerce to float.',
        );
        self::assertSame(
            1_700_000_000.123,
            $row->timestamp,
            'Numeric string must coerce to float.',
        );
        self::assertSame(
            4,
            $row->seq,
            'Numeric string must coerce to int.',
        );
        self::assertSame(
            2,
            $row->duplicate,
            'Numeric string must coerce to int.',
        );
        self::assertSame(
            99,
            $row->rows,
            "Numeric string must coerce to int for 'rows'.",
        );
    }

    public function testFromCollapsesNonArrayTraceFieldToEmptyList(): void
    {
        $row = QueryRowNormalizer::from(['trace' => 'not an array']);

        self::assertSame(
            [],
            $row->trace,
            "Non-array 'trace' must collapse to '[]'.",
        );
    }

    public function testFromDropsNonArrayTraceFrames(): void
    {
        $row = QueryRowNormalizer::from(
            [
                'trace' => [
                    ['file' => '/a.php'],
                    'invalid frame',
                    42,
                    ['file' => '/b.php'],
                ],
            ],
        );

        self::assertCount(
            2,
            $row->trace,
            'Non-array frames must be skipped.',
        );
        self::assertSame(
            ['file' => '/a.php'],
            $row->trace[0],
            'Surviving frames must keep their order.',
        );
        self::assertSame(
            ['file' => '/b.php'],
            $row->trace[1],
            'Surviving frames must keep their order.',
        );
    }

    public function testFromDropsNonStringFrameKeys(): void
    {
        $row = QueryRowNormalizer::from(
            [
                'trace' => [
                    [
                        'file' => '/a.php',
                        0 => 'numeric-key',
                        'line' => 5,
                    ],
                ],
            ],
        );

        self::assertCount(
            1,
            $row->trace,
            'Frame must be retained.',
        );
        self::assertSame(
            ['file' => '/a.php', 'line' => 5],
            $row->trace[0],
            'Numeric keys must be filtered out.',
        );
    }

    public function testFromFallsBackToEmptyStringWhenStringFieldsAreNotStrings(): void
    {
        $row = QueryRowNormalizer::from(
            [
                'type' => 42,
                'query' => null,
                'traceHash' => false,
            ],
        );

        self::assertSame(
            '',
            $row->type,
            "Non-string `type` must collapse to ''.",
        );
        self::assertSame(
            '',
            $row->query,
            "Non-string `query` must collapse to ''.",
        );
        self::assertSame(
            '',
            $row->traceHash,
            "Non-string `traceHash` must collapse to ''.",
        );
    }

    public function testFromFallsBackToZeroForNonNumericNumericFields(): void
    {
        $row = QueryRowNormalizer::from(
            [
                'duration' => 'abc',
                'timestamp' => null,
                'seq' => false,
            ],
        );

        self::assertSame(
            0.0,
            $row->duration,
            "Non-numeric 'duration' must collapse to '0.0'.",
        );
        self::assertSame(
            0.0,
            $row->timestamp,
            "Non-numeric 'timestamp' must collapse to '0.0'.",
        );
        self::assertSame(
            0,
            $row->seq,
            "Non-numeric 'seq' must collapse to '0'.",
        );
    }

    public function testFromKeepsRowsAsNullWhenAbsentOrNonNumeric(): void
    {
        $missing = QueryRowNormalizer::from(
            ['type' => 'SELECT'],
        );
        $string = QueryRowNormalizer::from(
            ['rows' => 'abc'],
        );
        $nullValue = QueryRowNormalizer::from(
            ['rows' => null],
        );

        self::assertNull(
            $missing->rows,
            "Missing 'rows' must remain 'null'.",
        );
        self::assertNull(
            $string->rows,
            "Non-numeric string 'rows' must remain 'null'.",
        );
        self::assertNull(
            $nullValue->rows,
            "Explicit 'null' must round-trip.",
        );
    }

    public function testFromReturnsAllZeroDefaultsWhenInputIsNotArray(): void
    {
        $row = QueryRowNormalizer::from(
            'not an array',
        );

        self::assertSame(
            '',
            $row->type,
            "Non-array input must yield empty 'type'.",
        );
        self::assertSame(
            '',
            $row->query,
            "Non-array input must yield empty 'query'.",
        );
        self::assertSame(
            0.0,
            $row->duration,
            "Non-array input must yield zero 'duration'.",
        );
        self::assertSame(
            [],
            $row->trace,
            "Non-array input must yield empty 'trace'.",
        );
        self::assertSame(
            '',
            $row->traceHash,
            "Non-array input must yield empty 'traceHash'.",
        );
        self::assertSame(
            0.0,
            $row->timestamp,
            "Non-array input must yield zero 'timestamp'.",
        );
        self::assertSame(
            0,
            $row->seq,
            "Non-array input must yield zero 'seq'.",
        );
        self::assertSame(
            1,
            $row->duplicate,
            "Missing 'duplicate' must collapse to the minimum value '1'.",
        );
        self::assertNull(
            $row->rows,
            "Non-array input must yield 'null' 'rows'.",
        );
    }

    public function testFromRoundTripsTypedRow(): void
    {
        $row = QueryRowNormalizer::from(
            [
                'type' => 'SELECT',
                'query' => 'SELECT 1',
                'duration' => 12.5,
                'trace' => [['file' => '/app/User.php', 'line' => 42]],
                'traceHash' => 'abc123',
                'timestamp' => 1_700_000_000_000.0,
                'seq' => 7,
                'duplicate' => 3,
                'rows' => 5,
            ],
        );

        self::assertSame(
            'SELECT',
            $row->type,
            'Type must round-trip.',
        );
        self::assertSame(
            'SELECT 1',
            $row->query,
            'Query must round-trip.',
        );
        self::assertSame(
            12.5,
            $row->duration,
            'Duration must round-trip.',
        );
        self::assertSame(
            [['file' => '/app/User.php', 'line' => 42]],
            $row->trace,
            'Trace must round-trip.',
        );
        self::assertSame(
            'abc123',
            $row->traceHash,
            'TraceHash must round-trip.',
        );
        self::assertSame(
            1_700_000_000_000.0,
            $row->timestamp,
            'Timestamp must round-trip.',
        );
        self::assertSame(
            7,
            $row->seq,
            'Seq must round-trip.',
        );
        self::assertSame(
            3,
            $row->duplicate,
            'Duplicate count must round-trip.',
        );
        self::assertSame(
            5,
            $row->rows,
            'Rows must round-trip.',
        );
    }
}
