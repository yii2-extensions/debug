<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\helpers\Format;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see Format} covering the megabyte readout and the trimmed CSS percentage formatter.
 */
#[Group('helpers')]
#[Group('format')]
final class FormatTest extends TestCase
{
    public function testBytesToMbFormatsWithRequestedPrecision(): void
    {
        self::assertSame(
            '2.00 MB',
            Format::bytesToMb(2_097_152),
            'Default precision must keep two decimals.',
        );
        self::assertSame(
            '2.000 MB',
            Format::bytesToMb(2_097_152, 3),
            'Explicit precision must widen the decimals.',
        );
    }

    public function testCssPercentTrimsTrailingZerosAndDot(): void
    {
        $cases = [
            '0%' => 0.0,
            '50%' => 50.0,
            '100%' => 100.0,
            '12.5%' => 12.5,
            '33.333%' => 1 / 3 * 100,
            '0.001%' => 0.001,
        ];

        foreach ($cases as $expected => $value) {
            self::assertSame(
                $expected,
                Format::cssPercent($value),
                "Value '{$value}' must format as '{$expected}'.",
            );
        }
    }
}
