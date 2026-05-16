<?php

declare(strict_types=1);

namespace yii\debug\tests\dump;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\dump\{DumpCardRenderer, DumpRow};
use yii\debug\panels\DumpPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DumpCardRenderer} covering the dump card composition: index badge, type sniff, time and trace
 * meta line, and the optional trace list.
 */
#[Group('panel')]
#[Group('dump')]
final class DumpCardRendererTest extends TestCase
{
    public function testRenderMessageCellEmitsIndexBadgeBasedOnIndex(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringContainsString(
            'class="yii-debug-dump-index"',
            $html,
            'Index badge must carry the dedicated class.',
        );
        self::assertStringContainsString(
            '#1',
            $html,
            'Index badge must show the 1-based row number.',
        );
    }

    public function testRenderMessageCellEmitsTraceListWhenTraceHasFrames(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringContainsString(
            'class="yii-debug-trace"',
            $html,
            "Trace list '<ul>' must carry the dedicated class.",
        );
        self::assertStringContainsString(
            'User.php',
            $html,
            'Trace list must render frame metadata.',
        );
    }

    public function testRenderMessageCellOmitsTimeWhenTimeIsZero(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-dump-time',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(time: 0.0),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            'Zero time must not produce a time span.',
        );
    }

    public function testRenderMessageCellOmitsTraceLabelWhenFirstFrameHasNoFile(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-dump-trace',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(trace: [['line' => 42]]),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            'Missing file in first frame must hide the trace label.',
        );
    }

    public function testRenderMessageCellOmitsTraceListWhenTraceIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-trace',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(trace: []),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            'Empty trace must omit the trace list.',
        );
    }

    public function testRenderMessageCellOmitsTypeBadgeWhenPayloadIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-dump-type',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: ''),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            'Empty payload must not produce a type badge.',
        );
    }

    public function testRenderMessageCellOmitsTypeBadgeWhenPayloadStartsWithSymbol(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php +42'),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringNotContainsString(
            'yii-debug-dump-type',
            $html,
            'Unrecognized payload prefix must not produce a type badge.',
        );
    }

    public function testRenderMessageCellRendersFormattedTimeWhenTimeIsPositive(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(time: 1_700_000_000.789),
            $this->makePanel(DumpPanel::class),
            0,
        );

        $expected = date('H:i:s', 1_700_000_000) . '.789';

        self::assertStringContainsString(
            'class="yii-debug-dump-time"',
            $html,
            'Time span must carry the dedicated class.',
        );
        self::assertStringContainsString(
            $expected,
            $html,
            "Time must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderMessageCellRendersTraceLabelWithBasenameAndLine(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringContainsString(
            'class="yii-debug-dump-trace"',
            $html,
            'Trace label span must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'User.php:42',
            $html,
            "Trace label must show 'basename:line'.",
        );
        self::assertStringContainsString(
            'title="/app/User.php:42"',
            $html,
            'Trace label tooltip must keep the full path.',
        );
    }

    public function testRenderMessageCellSniffsArrayTypeFromOpeningBracket(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php [1, 2, 3]'),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringContainsString(
            'data-type="array"',
            $html,
            "Array type key must be tagged via 'data-type'.",
        );
        self::assertStringContainsString(
            '>array<',
            $html,
            'Array label must be visible.',
        );
    }

    public function testRenderMessageCellSniffsBoolFromTrueOrFalseLiteral(): void
    {
        self::assertStringContainsString(
            'data-type="bool"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php true'),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            "'true' literal must be tagged as 'bool'.",
        );
        self::assertStringContainsString(
            'data-type="bool"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php false'),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            "'false' literal must be tagged as 'bool'.",
        );
    }

    public function testRenderMessageCellSniffsNullFromNullLiteral(): void
    {
        self::assertStringContainsString(
            'data-type="null"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php null'),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            "'null' literal must be tagged as 'null'.",
        );
    }

    public function testRenderMessageCellSniffsNumberFromLeadingDigit(): void
    {
        self::assertStringContainsString(
            'data-type="number"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php 42'),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            "'42' literal must be tagged as 'number'.",
        );
    }

    public function testRenderMessageCellSniffsObjectFromIdentifierAndKeepsTheClassName(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php yii\\base\\Component#42'),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringContainsString(
            'data-type="object"',
            $html,
            "Identifier payload must be tagged as 'object'.",
        );
        self::assertStringContainsString(
            'yii\\base\\Component',
            $html,
            'The full class identifier must be exposed as the badge label.',
        );
    }

    public function testRenderMessageCellSniffsStringTypeFromQuoteCharacter(): void
    {
        self::assertStringContainsString(
            'data-type="string"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: "&lt;?php 'hello'"),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            "Single-quoted payload must be tagged as 'string'.",
        );
        self::assertStringContainsString(
            'data-type="string"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php "hello"'),
                $this->makePanel(DumpPanel::class),
                0,
            ),
            "Double-quoted payload must be tagged as 'string'.",
        );
    }

    public function testRenderMessageCellWrapsPayloadInTheDumpCardContainer(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php "x"'),
            $this->makePanel(DumpPanel::class),
            0,
        );

        self::assertStringContainsString(
            'class="yii-debug-dump"',
            $html,
            'Outer wrapper class must be present.',
        );
        self::assertStringContainsString(
            'class="yii-debug-dump-body"',
            $html,
            'Body wrapper class must be present.',
        );
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function makeRow(
        string $message = '&lt;?php "hello"',
        string $category = 'application',
        float $time = 0.0,
        array $trace = [],
    ): DumpRow {
        return new DumpRow(
            message: $message,
            category: $category,
            time: $time,
            trace: $trace,
        );
    }
}
