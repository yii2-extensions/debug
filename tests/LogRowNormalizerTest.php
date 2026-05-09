<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\log\LogRowNormalizer;
use yii\log\Logger;

/**
 * Unit tests for {@see LogRowNormalizer} covering the narrowing of GridView callback arguments into a typed
 * {@see \yii\debug\panels\log\LogRow}, including the `mixed` message exported via {@see \yii\helpers\VarDumper}.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('log')]
final class LogRowNormalizerTest extends TestCase
{
    public function testFromCoercesNumericStringsToIntAndFloat(): void
    {
        $row = LogRowNormalizer::from(
            [
                'id' => '7',
                'level' => '4',
                'time' => '1700000000500',
                'time_of_previous' => '1700000000000',
                'id_of_previous' => '6',
                'id_of_next' => '8',
            ],
        );

        self::assertSame(
            7,
            $row->id,
            "Numeric string 'id' must coerce to int.",
        );
        self::assertSame(
            4,
            $row->level,
            "Numeric string 'level' must coerce to int.",
        );
        self::assertSame(
            1_700_000_000_500.0,
            $row->time,
            "Numeric string 'time' must coerce to float.",
        );
        self::assertSame(
            6,
            $row->idOfPrevious,
            "Numeric string 'idOfPrevious' must coerce to int.",
        );
        self::assertSame(
            8,
            $row->idOfNext,
            "Numeric string 'idOfNext' must coerce to int.",
        );
    }

    public function testFromCollapsesNonArrayTraceFieldToEmptyList(): void
    {
        self::assertSame(
            [],
            LogRowNormalizer::from(['trace' => 'not an array'])->trace,
            "Non-array trace must yield '[]'."
        );
        self::assertSame(
            [],
            LogRowNormalizer::from(['trace' => null])->trace,
            "Null trace must yield '[]'."
        );
    }

    public function testFromDropsNonArrayTraceFramesAndNonStringFrameKeys(): void
    {
        $row = LogRowNormalizer::from(
            [
                'trace' => [
                    ['file' => '/a.php', 0 => 'numeric-key', 'line' => 5],
                    'invalid frame',
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
            ['file' => '/a.php', 'line' => 5],
            $row->trace[0],
            'Numeric keys must be filtered out.',
        );
    }

    public function testFromExportsNonStringMessageViaVarDumper(): void
    {
        $arrayMessage = LogRowNormalizer::from(
            ['message' => ['foo' => 'bar']],
        )->message;

        self::assertStringContainsString(
            "'foo'",
            $arrayMessage,
            'Arrays must be exported through VarDumper.',
        );
        self::assertStringContainsString(
            "'bar'",
            $arrayMessage,
            'Arrays must include their values when exported.',
        );
        self::assertSame(
            '42',
            LogRowNormalizer::from(['message' => 42])->message,
            'Integers must be exported as their literal representation.',
        );
        self::assertSame(
            'true',
            LogRowNormalizer::from(['message' => true])->message,
            'Booleans must be exported as `true` / `false` literals.',
        );
    }

    public function testFromKeepsIdOfPreviousAndNextNullWhenAbsentOrNonNumeric(): void
    {
        $missing = LogRowNormalizer::from(
            [],
        );
        $invalid = LogRowNormalizer::from(
            [
                'id_of_previous' => 'abc',
                'id_of_next' => null,
            ],
        );

        self::assertNull(
            $missing->idOfPrevious,
            "Missing 'idOfPrevious' must remain 'null'.",
        );
        self::assertNull(
            $missing->idOfNext,
            "Missing 'idOfNext' must remain 'null'.",
        );
        self::assertNull(
            $invalid->idOfPrevious,
            "Non-numeric 'idOfPrevious' must remain 'null'.",
        );
        self::assertNull(
            $invalid->idOfNext,
            "Explicit 'null' 'idOfNext' must remain 'null'.",
        );
    }

    public function testFromReturnsAllZeroDefaultsWhenInputIsNotArray(): void
    {
        $row = LogRowNormalizer::from(
            'not an array',
        );

        self::assertSame(
            0,
            $row->id,
            "Non-array input must yield zero 'id'.",
        );
        self::assertSame(
            'null',
            $row->message,
            "Non-array input must export 'null' as 'null'.",
        );
        self::assertSame(
            0,
            $row->level,
            "Non-array input must yield zero 'level'.",
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
            0.0,
            $row->timeOfPrevious,
            "Non-array input must yield zero 'timeOfPrevious'.",
        );
        self::assertNull(
            $row->idOfPrevious,
            "Non-array input must yield 'null' 'idOfPrevious'.",
        );
        self::assertNull(
            $row->idOfNext,
            "Non-array input must yield 'null' 'idOfNext'.",
        );
        self::assertSame(
            [],
            $row->trace,
            "Non-array input must yield empty 'trace'.",
        );
    }

    public function testFromRoundTripsTypedRow(): void
    {
        $row = LogRowNormalizer::from(
            [
                'id' => 7,
                'message' => 'Something happened',
                'level' => Logger::LEVEL_WARNING,
                'category' => 'application',
                'time' => 1_700_000_000_500.0,
                'time_of_previous' => 1_700_000_000_000.0,
                'id_of_previous' => 6,
                'id_of_next' => 8,
                'trace' => [['file' => '/app/User.php', 'line' => 42]],
            ],
        );

        self::assertSame(
            7,
            $row->id,
            'Id must round-trip.',
        );
        self::assertSame(
            'Something happened',
            $row->message,
            'String message must round-trip.',
        );
        self::assertSame(
            Logger::LEVEL_WARNING,
            $row->level,
            'Level must round-trip.',
        );
        self::assertSame(
            'application',
            $row->category,
            'Category must round-trip.',
        );
        self::assertSame(
            1_700_000_000_500.0,
            $row->time,
            'Time must round-trip.',
        );
        self::assertSame(
            1_700_000_000_000.0,
            $row->timeOfPrevious,
            "'timeOfPrevious' must round-trip.",
        );
        self::assertSame(
            6,
            $row->idOfPrevious,
            "'idOfPrevious' must round-trip.",
        );
        self::assertSame(
            8,
            $row->idOfNext,
            "'idOfNext' must round-trip.",
        );
        self::assertSame(
            [['file' => '/app/User.php', 'line' => 42]],
            $row->trace,
            'Trace must round-trip.',
        );
    }
}
