<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\profile\{ProfileCellRenderer, ProfileRow};

/**
 * Unit tests for {@see ProfileCellRenderer} covering the typed cell renderers used by the profile grid (time
 * formatting, duration formatting, indented info cell with HTML-escaped content).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileCellRendererTest extends TestCase
{
    public function testRenderDurationCellFormatsDurationToOneDecimalMillisecond(): void
    {
        self::assertSame(
            '12.5 ms',
            ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 12.5)),
            'Duration must keep one decimal.',
        );
        self::assertSame(
            '0.0 ms',
            ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 0.0)),
            "Zero duration must render as '0.0 ms'.",
        );
    }

    public function testRenderInfoCellEmitsOneIndentArrowPerLevel(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(info: 'nested', level: 3),
        );

        self::assertSame(
            3,
            substr_count($html, 'yii-debug-indent'),
            'Each nesting level must add one indentation arrow.',
        );
        self::assertSame(
            3,
            substr_count($html, '→'),
            'Each indentation arrow must contain the chevron glyph.',
        );
        self::assertStringContainsString('nested', $html, 'Info text must be visible after the indentation arrows.');
    }

    public function testRenderInfoCellEscapesInfoText(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(info: '<script>alert(1)</script>', level: 0),
        );

        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Info content must be HTML-escaped.',
        );
        self::assertStringNotContainsString(
            '<script>alert',
            $html,
            'Raw script tags must not leak into the output.',
        );
    }

    public function testRenderInfoCellOmitsIndentArrowsAtLevelZero(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(info: 'root', level: 0),
        );

        self::assertStringNotContainsString(
            'yii-debug-indent',
            $html,
            "Level '0' must not produce indentation arrows.",
        );
        self::assertStringContainsString(
            'root',
            $html,
            "Info text must be visible at level '0'.",
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $expected = date('H:i:s', 1_700_000_000) . '.789';

        $html = ProfileCellRenderer::renderTimeCell(
            self::makeRow(timestamp: 1_700_000_000_789.0),
        );

        self::assertSame(
            $expected,
            $html,
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellPadsMillisecondsWithLeadingZeros(): void
    {
        $expected = date('H:i:s', 1_700_000_000) . '.005';

        $html = ProfileCellRenderer::renderTimeCell(
            self::makeRow(timestamp: 1_700_000_000_005.0),
        );

        self::assertSame(
            $expected,
            $html,
            "Milliseconds below '100' must be zero-padded to three digits.",
        );
    }

    private static function makeRow(
        float $timestamp = 0.0,
        float $duration = 1.0,
        string $category = 'app',
        string $info = 'token',
        int $level = 0,
        int $seq = 0,
    ): ProfileRow {
        return new ProfileRow(
            timestamp: $timestamp,
            duration: $duration,
            category: $category,
            info: $info,
            level: $level,
            seq: $seq,
        );
    }
}
