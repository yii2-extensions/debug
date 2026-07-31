<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\HistoryScale;

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
                ['processingTime' => null, 'peakMemory' => null],
                ['processingTime' => 0.125, 'peakMemory' => 1_048_576],
                ['processingTime' => 0.5, 'peakMemory' => 2_097_152],
                ['processingTime' => 0.25],
                'garbage-row',
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
            [
                ['tag' => 'a'],
                ['tag' => 'b', 'processingTime' => null, 'peakMemory' => null],
            ],
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
}
