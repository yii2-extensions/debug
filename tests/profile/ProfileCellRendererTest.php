<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPForge\Debug\Helper\CellMore;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\profile\{ProfileCellRenderer, ProfileRow};
use yii\debug\tests\support\TestCase;

use function str_repeat;

/**
 * Unit tests for {@see ProfileCellRenderer} covering the typed cell renderers used by the profile grid (time
 * formatting with the hover tooltip, duration formatting, two-tone category label, and the indented info cell with
 * its SQL highlighting and long-statement clamp).
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileCellRendererTest extends TestCase
{
    public function testRenderCategoryCellKeepsMethodSuffixInsideStrongShortName(): void
    {
        self::assertStringContainsString(
            '<strong>Command::query</strong>',
            ProfileCellRenderer::renderCategoryCell(self::makeRow(category: 'yii\\db\\Command::query')),
            'Method pair must render bold as one segment.',
        );
    }

    public function testRenderCategoryCellRendersPlainCategoryWithoutMutedPrefix(): void
    {
        $cell = ProfileCellRenderer::renderCategoryCell(self::makeRow(category: 'application'));

        self::assertStringContainsString(
            '<strong>application</strong>',
            $cell,
            'Plain category must render bold.',
        );
        self::assertStringNotContainsString(
            'yii-debug-muted',
            $cell,
            'Plain categories must not emit a namespace prefix.',
        );
    }

    public function testRenderCategoryCellSplitsFqcnCategoryIntoMutedNamespaceAndStrongShortName(): void
    {
        $cell = ProfileCellRenderer::renderCategoryCell(self::makeRow(category: 'yii\\db\\Command::query'));

        self::assertStringContainsString(
            'yii-debug-muted',
            $cell,
            'Namespace prefix must render muted.',
        );
        self::assertStringContainsString(
            'title="yii\db\Command::query"',
            $cell,
            'Full category must sit in the `title` attribute.',
        );
    }

    public function testRenderDurationCellFormatsDurationToOneDecimalMillisecond(): void
    {
        self::assertSame(
            '12.5 ms',
            ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 12.5), 0.0),
            'Duration must keep one decimal.',
        );
        self::assertSame(
            '0.0 ms',
            ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 0.0), 0.0),
            "Zero duration must render as '0.0 ms'.",
        );
    }

    public function testRenderDurationCellScalesGaugeAgainstCaptureMaximum(): void
    {
        $html = ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 12.5), 25.0);

        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">12.5 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            $html,
            'Rail must sit at half the capture maximum.',
        );
    }

    public function testRenderInfoCellClampsLongStatements(): void
    {
        $long = 'SELECT ' . str_repeat('a', CellMore::THRESHOLD);
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(category: 'yii\\db\\Command::query', info: $long));

        self::assertStringContainsString(
            'yii-debug-cell-more',
            $html,
            'A long statement must collapse behind the clamp.',
        );
        self::assertStringContainsString(
            str_repeat('a', CellMore::THRESHOLD),
            $html,
            'Clamping must not truncate the statement.',
        );
    }

    public function testRenderInfoCellEmitsOneIndentArrowPerLevel(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(info: 'nested', level: 3));

        self::assertSame(
            str_repeat('<span class="yii-debug-indent">→</span>', 3) . 'nested',
            $html,
            'Indentation arrows must precede the profile token.',
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
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(info: '<script>alert(1)</script>', level: 0));

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

    public function testRenderInfoCellHighlightsSqlForDbCommandBlocks(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(category: 'yii\\db\\Command::query', info: 'SELECT * FROM "user"'),
        );

        self::assertStringContainsString(
            'yii-debug-db-sql',
            $html,
            'SQL must reuse the queries-grid presentation.',
        );
        self::assertStringContainsString(
            'yii-debug-sql-kw',
            $html,
            'Keywords must carry their token span.',
        );
    }

    public function testRenderInfoCellKeepsPlainInfoUnhighlighted(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(category: 'application', info: 'SELECT me'));

        self::assertStringNotContainsString(
            'yii-debug-db-sql',
            $html,
            'Only DB command blocks may highlight.',
        );
    }

    public function testRenderInfoCellLeavesShortStatementsUnclamped(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(category: 'yii\\db\\Command::query', info: 'SELECT 1'),
        );

        self::assertStringNotContainsString(
            'yii-debug-cell-more',
            $html,
            'Short statements must stay inline.',
        );
    }

    public function testRenderInfoCellOmitsIndentArrowsAtLevelZero(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(info: 'root', level: 0));

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

    public function testRenderTimeCellExposesFullTimestampInTitleAttribute(): void
    {
        $expected = date('Y-m-d H:i:s', 1_700_000_000) . '.789';

        self::assertStringContainsString(
            "title=\"{$expected}\"",
            ProfileCellRenderer::renderTimeCell(self::makeRow(timestamp: 1_700_000_000_789.0)),
            'Full timestamp must sit in the `title` attribute.',
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $expected = date('H:i:s', 1_700_000_000) . '.789';

        $html = ProfileCellRenderer::renderTimeCell(self::makeRow(timestamp: 1_700_000_000_789.0));

        self::assertStringContainsString(
            ">{$expected}</span>",
            $html,
            "Visible text must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellKeepsMillisecondsBelowTheNextBoundary(): void
    {
        $html = ProfileCellRenderer::renderTimeCell(self::makeRow(timestamp: 1_500.5));

        self::assertStringContainsString(
            '>' . date('H:i:s', 1) . '.500</span>',
            $html,
            'Sub-millisecond fractions must not advance the rendered millisecond value.',
        );
    }

    public function testRenderTimeCellPadsMillisecondsWithLeadingZeros(): void
    {
        $expected = date('H:i:s', 1_700_000_000) . '.005';

        $html = ProfileCellRenderer::renderTimeCell(
            self::makeRow(timestamp: 1_700_000_000_005.0),
        );

        self::assertStringContainsString(
            ">{$expected}</span>",
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
            memory: 0,
            memoryDiff: 0,
            trace: [],
        );
    }
}
