<?php

declare(strict_types=1);

namespace yii\debug\tests\dump;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\dump\DumpRowNormalizer;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DumpRowNormalizer} covering the narrowing of GridView callback arguments into a typed
 * {@see \yii\debug\panels\dump\DumpRow}.
 */
#[Group('panel')]
#[Group('dump')]
final class DumpRowNormalizerTest extends TestCase
{
    public function testFromCoercesNumericStringToFloatTime(): void
    {
        $row = DumpRowNormalizer::from(
            ['time' => '1700000000.5'],
        );

        self::assertSame(
            1_700_000_000.5,
            $row->time,
            'Numeric string must coerce to float.',
        );
    }

    public function testFromCollapsesNonArrayTraceFieldToEmptyList(): void
    {
        $row = DumpRowNormalizer::from(
            ['trace' => 'not an array'],
        );

        self::assertSame(
            [],
            $row->trace,
            "Non-array 'trace' must collapse to '[]'.",
        );
    }

    public function testFromDropsNonArrayTraceFrames(): void
    {
        $row = DumpRowNormalizer::from(
            [
                'trace' => [
                    ['file' => '/a.php'],
                    'invalid',
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
        $row = DumpRowNormalizer::from(
            [
                'trace' => [
                    [
                        'file' => '/a.php',
                        0 => 'skip',
                        'line' => 5],
                ],
            ],
        );

        $first = $row->trace[0] ?? self::fail('Expected at least one trace frame.');

        self::assertSame(
            ['file' => '/a.php', 'line' => 5],
            $first,
            'Numeric keys must be filtered out.',
        );
    }

    public function testFromFallsBackToEmptyStringWhenScalarFieldsAreNotStrings(): void
    {
        $row = DumpRowNormalizer::from(
            ['message' => 42, 'category' => null],
        );

        self::assertSame(
            '',
            $row->message,
            "Non-string 'message' must collapse to ''.",
        );
        self::assertSame(
            '',
            $row->category,
            "Non-string 'category' must collapse to ''.",
        );
    }

    public function testFromFallsBackToZeroWhenTimeIsNotNumeric(): void
    {
        $row = DumpRowNormalizer::from(
            ['time' => 'abc'],
        );

        self::assertSame(
            0.0,
            $row->time,
            "Non-numeric 'time' must collapse to '0.0'.",
        );
    }

    public function testFromReturnsAllZeroDefaultsWhenInputIsNotArray(): void
    {
        $row = DumpRowNormalizer::from(
            'not an array',
        );

        self::assertSame(
            '',
            $row->message,
            "Non-array input must yield empty 'message'.",
        );
        self::assertSame(
            '',
            $row->category,
            "Non-array input must yield empty 'category'.",
        );
        self::assertSame(
            0.0,
            $row->time,
            "Non-array input must yield zero 'time'.",
        );
        self::assertSame(
            [],
            $row->trace,
            "Non-array input must yield empty 'trace'.",
        );
    }

    public function testFromRoundTripsTypedRow(): void
    {
        $row = DumpRowNormalizer::from(
            [
                'message' => '<?php "hello"',
                'category' => 'application',
                'time' => 1_700_000_000.123,
                'trace' => [
                    [
                        'file' => '/app/User.php',
                        'line' => 42,
                    ],
                ],
            ],
        );

        self::assertSame(
            '<?php "hello"',
            $row->message,
            'Message must round-trip.',
        );
        self::assertSame(
            'application',
            $row->category,
            'Category must round-trip.',
        );
        self::assertSame(
            1_700_000_000.123,
            $row->time,
            'Time must round-trip.',
        );
        self::assertSame(
            [['file' => '/app/User.php', 'line' => 42]],
            $row->trace,
            'Trace must round-trip.',
        );
    }
}
