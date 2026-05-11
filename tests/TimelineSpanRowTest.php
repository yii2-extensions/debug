<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\timeline\TimelineSpanRow;

/**
 * Unit tests for {@see TimelineSpanRow} covering loose-array narrowing, category → CSS-variant mapping, the
 * minimum-width floor and the multi-line tooltip composition.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineSpanRowTest extends TestCase
{
    public function testFromClampsBarWidthToVisibleFloor(): void
    {
        $row = TimelineSpanRow::from(
            [
                'category' => 'x',
                'css' => [
                    'width' => 0.1,
                    'left' => 5,
                ],
            ],
        );

        self::assertSame(
            '0.4',
            $row->cssWidth,
            "Sub-floor widths must clamp to '0.4' to stay visible.",
        );
    }

    public function testFromComposesTooltipWithMemoryDeltaWhenNonZero(): void
    {
        $row = TimelineSpanRow::from(
            [
                'category' => 'yii\\db\\Command::query',
                'info' => 'SELECT *',
                'duration' => 12.345,
                'memory' => 1572864,
                'memoryDiff' => 1048576,
            ],
        );

        self::assertStringContainsString(
            'SELECT *',
            $row->tooltip,
            'Info text must precede the duration line in the tooltip.',
        );
        self::assertStringContainsString(
            '12.345 ms',
            $row->tooltip,
            'Duration must render with three decimals.',
        );
        self::assertStringContainsString(
            '1.50 MB',
            $row->tooltip,
            'Memory must render as MB with two decimals.',
        );
        self::assertStringContainsString(
            '(+1.00 MB)',
            $row->tooltip,
            "Positive memory delta must show a '+' sign.",
        );
    }

    public function testFromComposesTooltipWithNegativeMemoryDeltaSign(): void
    {
        $row = TimelineSpanRow::from(
            [
                'category' => 'x',
                'duration' => 1.0,
                'memory' => 1048576,
                'memoryDiff' => -1048576,
            ],
        );

        self::assertStringContainsString(
            '(−1.00 MB)',
            $row->tooltip,
            'Negative delta must use a minus sign character.',
        );
    }

    public function testFromComposesTooltipWithoutMemoryDeltaWhenZero(): void
    {
        $row = TimelineSpanRow::from(
            [
                'category' => 'x',
                'duration' => 1.0,
                'memory' => 0,
            ],
        );

        self::assertStringNotContainsString(
            '(',
            $row->tooltip,
            'Zero delta must omit the parenthesized chip.',
        );
    }

    public function testFromFallsBackToCategoryWhenInfoIsMissing(): void
    {
        $row = TimelineSpanRow::from(
            [
                'category' => 'yii\\db\\Command::query',
                'duration' => 1.0,
            ],
        );

        self::assertStringStartsWith(
            'yii\\db\\Command::query',
            $row->tooltip,
            'Missing info must fall back to the category for the tooltip heading.',
        );
    }

    public function testFromMapsCacheCategoryToSuccessVariant(): void
    {
        self::assertSame(
            'success',
            TimelineSpanRow::from(['category' => 'yii\\caching\\FileCache::get'])->variant
        );
        self::assertSame(
            'success',
            TimelineSpanRow::from(['category' => 'cache.something'])->variant
        );
    }

    public function testFromMapsDbCategoryToInfoVariant(): void
    {
        self::assertSame(
            'info',
            TimelineSpanRow::from(['category' => 'yii\\db\\Command::query'])->variant
        );
        self::assertSame(
            'info',
            TimelineSpanRow::from(['category' => 'SomeCommand::execute'])->variant
        );
    }

    public function testFromMapsMailQueueCategoryToDangerVariant(): void
    {
        self::assertSame(
            'danger',
            TimelineSpanRow::from(['category' => 'app\\jobs\\mail'])->variant
        );
        self::assertSame(
            'danger',
            TimelineSpanRow::from(['category' => 'queue.push'])->variant
        );
    }

    public function testFromMapsUnknownCategoryToMutedVariant(): void
    {
        self::assertSame(
            'muted',
            TimelineSpanRow::from(['category' => 'app\\custom'])->variant
        );
        self::assertSame(
            'muted',
            TimelineSpanRow::from(['category' => ''])->variant
        );
    }

    public function testFromMapsViewRenderTwigCategoryToWarningVariant(): void
    {
        self::assertSame(
            'warning',
            TimelineSpanRow::from(['category' => 'yii\\base\\View::render'])->variant
        );
        self::assertSame(
            'warning',
            TimelineSpanRow::from(['category' => 'twig.render'])->variant
        );
    }

    public function testFromNarrowsMissingFieldsToSafeDefaults(): void
    {
        $row = TimelineSpanRow::from([]);

        self::assertSame(
            '',
            $row->category,
            'Missing category must default to empty string.',
        );
        self::assertSame(
            0.0,
            $row->duration,
            "Missing duration must default to '0.0'.",
        );
        self::assertSame(
            0,
            $row->depth,
            "Missing child depth must default to '0'.",
        );
        self::assertSame(
            '0',
            $row->cssLeft,
            "Missing left must default to '0'.",
        );
        self::assertSame(
            '0.4',
            $row->cssWidth,
            "Missing width must default to the floor '0.4'.",
        );
        self::assertSame(
            'muted',
            $row->variant,
            'Missing category must yield the muted variant.',
        );
    }
}
