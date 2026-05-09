<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\log\LogCountsNormalizer;
use yii\log\Logger;

/**
 * Unit tests for {@see LogCountsNormalizer} covering level totals derived from raw `$panel->data['messages']`.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('log')]
final class LogCountsNormalizerTest extends TestCase
{
    public function testFromPanelDataAggregatesLevelsCorrectly(): void
    {
        $counts = LogCountsNormalizer::fromPanelData(
            [
                'messages' => [
                    ['ok', Logger::LEVEL_INFO, 'app', 1.0],
                    ['boom', Logger::LEVEL_ERROR, 'app', 1.1],
                    ['careful', Logger::LEVEL_WARNING, 'app', 1.2],
                    ['boom2', Logger::LEVEL_ERROR, 'app', 1.3],
                    ['trace', Logger::LEVEL_TRACE, 'app', 1.4],
                ],
            ],
        );

        self::assertSame(
            5,
            $counts->total,
            'Total must reflect every entry regardless of level.',
        );
        self::assertSame(
            2,
            $counts->errors,
            "Error count must reflect 'LEVEL_ERROR' entries.",
        );
        self::assertSame(
            1,
            $counts->warnings,
            "Warning count must reflect 'LEVEL_WARNING' entries.",
        );
        self::assertSame(
            1,
            $counts->info,
            "Info count must reflect 'LEVEL_INFO' entries.",
        );
    }

    public function testFromPanelDataCoercesNumericStringLevels(): void
    {
        $counts = LogCountsNormalizer::fromPanelData(
            [
                'messages' => [
                    [
                        'ok',
                        (string) Logger::LEVEL_ERROR,
                        'app',
                        1.0,
                    ],
                ],
            ],
        );

        self::assertSame(
            1,
            $counts->errors,
            'Numeric-string level must coerce to int.',
        );
    }

    public function testFromPanelDataExposesHasFlagsForNonZeroCounts(): void
    {
        $counts = LogCountsNormalizer::fromPanelData(
            [
                'messages' => [
                    [
                        'boom',
                        Logger::LEVEL_ERROR,
                        'app',
                        1.0,
                    ],
                ],
            ],
        );

        self::assertTrue(
            $counts->hasErrors(),
            "'hasErrors' must reflect a non-zero error count.",
        );
        self::assertFalse(
            $counts->hasWarnings(),
            "'hasWarnings' must remain 'false' when no warnings were captured.",
        );
        self::assertFalse(
            $counts->hasInfo(),
            "'hasInfo' must remain 'false' when no info entries were captured.",
        );
    }

    public function testFromPanelDataReturnsAllZeroCountsWhenInputIsNotArray(): void
    {
        $counts = LogCountsNormalizer::fromPanelData(
            'not an array',
        );

        self::assertSame(
            0,
            $counts->total,
            "Non-array input must yield zero 'total'.",
        );
        self::assertSame(
            0,
            $counts->errors,
            "Non-array input must yield zero 'errors'.",
        );
        self::assertSame(
            0,
            $counts->warnings,
            "Non-array input must yield zero 'warnings'.",
        );
        self::assertSame(
            0,
            $counts->info,
            "Non-array input must yield zero 'info'.",
        );
    }

    public function testFromPanelDataReturnsAllZeroCountsWhenMessagesAreMissing(): void
    {
        $counts = LogCountsNormalizer::fromPanelData(
            [],
        );

        self::assertSame(
            0,
            $counts->total,
            "Missing 'messages' key must yield zero 'total'.",
        );
    }

    public function testFromPanelDataSkipsNonArrayEntries(): void
    {
        $counts = LogCountsNormalizer::fromPanelData(
            [
                'messages' => [
                    ['ok', Logger::LEVEL_INFO, 'app', 1.0],
                    'invalid entry',
                    42,
                    ['boom', Logger::LEVEL_ERROR, 'app', 1.1],
                ],
            ],
        );

        self::assertSame(
            2,
            $counts->total,
            'Non-array entries must be skipped.',
        );
        self::assertSame(
            1,
            $counts->errors,
            'Surviving error entry must be counted.',
        );
    }
}
