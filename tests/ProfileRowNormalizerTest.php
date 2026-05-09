<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\profile\ProfileRowNormalizer;

/**
 * Unit tests for {@see ProfileRowNormalizer} covering the narrowing of GridView callback arguments into a typed
 * {@see \yii\debug\panels\profile\ProfileRow}.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileRowNormalizerTest extends TestCase
{
    public function testFromClampsNegativeLevelToZero(): void
    {
        self::assertSame(
            0,
            ProfileRowNormalizer::from(['level' => -3])->level,
            "Negative level must clamp to '0'.",
        );
        self::assertSame(
            0,
            ProfileRowNormalizer::from(['level' => -1])->level,
            "Negative level must clamp to '0'.",
        );
    }

    public function testFromCoercesNumericStringsToFloatAndInt(): void
    {
        $row = ProfileRowNormalizer::from(
            [
                'timestamp' => '1700000000500',
                'duration' => '12.5',
                'level' => '3',
                'seq' => '4',
            ],
        );

        self::assertSame(
            1_700_000_000_500.0,
            $row->timestamp,
            'Numeric string timestamp must coerce to float.',
        );
        self::assertSame(
            12.5,
            $row->duration,
            'Numeric string duration must coerce to float.',
        );
        self::assertSame(
            3,
            $row->level,
            'Numeric string level must coerce to int.',
        );
        self::assertSame(
            4,
            $row->seq,
            'Numeric string seq must coerce to int.',
        );
    }

    public function testFromFallsBackToEmptyStringWhenStringFieldsAreNotStrings(): void
    {
        $row = ProfileRowNormalizer::from(
            [
                'category' => 42,
                'info' => null,
            ],
        );

        self::assertSame(
            '',
            $row->category,
            "Non-string `category` must collapse to ''.",
        );
        self::assertSame(
            '',
            $row->info,
            "Non-string `info` must collapse to ''.",
        );
    }

    public function testFromFallsBackToZeroForNonNumericFields(): void
    {
        $row = ProfileRowNormalizer::from(
            [
                'timestamp' => 'abc',
                'duration' => null,
                'level' => false,
                'seq' => 'xyz',
            ],
        );

        self::assertSame(
            0.0,
            $row->timestamp,
            "Non-numeric 'timestamp' must collapse to '0.0'.",
        );
        self::assertSame(
            0.0,
            $row->duration,
            "Non-numeric 'duration' must collapse to '0.0'.",
        );
        self::assertSame(
            0,
            $row->level,
            "Non-numeric 'level' must collapse to '0'.",
        );
        self::assertSame(
            0,
            $row->seq,
            "Non-numeric 'seq' must collapse to '0'.",
        );
    }

    public function testFromKeepsNonNegativeLevelAsIs(): void
    {
        self::assertSame(
            0,
            ProfileRowNormalizer::from(['level' => 0])->level,
            "'0' level must round-trip.",
        );
        self::assertSame(
            5,
            ProfileRowNormalizer::from(['level' => 5])->level,
            "'5' level must round-trip.",
        );
    }

    public function testFromReturnsAllZeroDefaultsWhenInputIsNotArray(): void
    {
        $row = ProfileRowNormalizer::from(
            'not an array',
        );

        self::assertSame(
            0.0,
            $row->timestamp,
            "Non-array input must yield zero 'timestamp'.",
        );
        self::assertSame(
            0.0,
            $row->duration,
            "Non-array input must yield zero 'duration'.",
        );
        self::assertSame(
            '',
            $row->category,
            "Non-array input must yield empty 'category'.",
        );
        self::assertSame(
            '',
            $row->info,
            "Non-array input must yield empty 'info'.",
        );
        self::assertSame(
            0,
            $row->level,
            "Non-array input must yield zero 'level'.",
        );
        self::assertSame(
            0,
            $row->seq,
            "Non-array input must yield zero 'seq'.",
        );
    }

    public function testFromRoundTripsTypedRow(): void
    {
        $row = ProfileRowNormalizer::from(
            [
                'timestamp' => 1_700_000_000_500.0,
                'duration' => 12.5,
                'category' => 'yii\\db\\Command::query',
                'info' => 'SELECT * FROM users',
                'level' => 2,
                'seq' => 7,
            ],
        );

        self::assertSame(
            1_700_000_000_500.0,
            $row->timestamp,
            'Timestamp must round-trip.',
        );
        self::assertSame(
            12.5,
            $row->duration,
            'Duration must round-trip.',
        );
        self::assertSame(
            'yii\\db\\Command::query',
            $row->category,
            'Category must round-trip.',
        );
        self::assertSame(
            'SELECT * FROM users',
            $row->info,
            'Info must round-trip.',
        );
        self::assertSame(
            2,
            $row->level,
            'Level must round-trip.',
        );
        self::assertSame(
            7,
            $row->seq,
            'Seq must round-trip.',
        );
    }
}
