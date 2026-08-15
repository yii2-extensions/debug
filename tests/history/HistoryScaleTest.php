<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\{HistoryRow, HistoryScale};

/**
 * Unit tests for {@see HistoryScale} covering the page-maxima scan behind the History micro-gauges.
 */
#[Group('history')]
final class HistoryScaleTest extends TestCase
{
    public function testFromModelsIgnoresRowsWithoutCapturedValues(): void
    {
        $scale = HistoryScale::fromModels(
            [
                self::row(null, null),
                self::row(0.125, 1_048_576),
                self::row(0.5, 2_097_152),
                self::row(0.25, null),
            ],
        );

        self::assertSame(
            0.5,
            $scale->maxProcessingTime,
            'Largest captured duration must win.',
        );
        self::assertSame(
            2_097_152,
            $scale->maxPeakMemory,
            'Largest captured memory must win.',
        );
    }

    public function testFromModelsReturnsZeroMaximaForEmptyList(): void
    {
        $scale = HistoryScale::fromModels([]);

        self::assertSame(
            0.0,
            $scale->maxProcessingTime,
            'Empty pages must report a `0.0` duration scale.',
        );
        self::assertSame(
            0,
            $scale->maxPeakMemory,
            'Empty pages must report a `0` memory scale.',
        );
    }

    public function testFromModelsReturnsZeroMaximaWhenNoRowCarriesValues(): void
    {
        $scale = HistoryScale::fromModels(
            [self::row(null, null), self::row(null, null)],
        );

        self::assertSame(
            0.0,
            $scale->maxProcessingTime,
            'All-`null` durations must collapse the scale to `0.0`.',
        );
        self::assertSame(
            0,
            $scale->maxPeakMemory,
            'All-`null` memory must collapse the scale to `0`.',
        );
    }

    private static function row(float|null $processingTime, int|null $peakMemory): HistoryRow
    {
        return HistoryRow::fromSummary(
            RequestSummary::fromArray(
                [
                    'tag' => 'tag-1',
                    'url' => 'https://example.test/',
                    'ajax' => false,
                    'method' => 'GET',
                    'ip' => '127.0.0.1',
                    'time' => 1_700_000_000.0,
                    'statusCode' => 200,
                    'sqlCount' => 0,
                    'excessiveCallersCount' => 0,
                    'mailCount' => 0,
                    'mailFiles' => [],
                    'processingTime' => $processingTime,
                    'peakMemory' => $peakMemory,
                ],
            ),
        );
    }
}
