<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\event\{EventCellRenderer, EventRow};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventCellRenderer} covering the typed cell renderers used by the events grid (time formatting,
 * FQCN splitting for the class/sender cells, and the static badge).
 */
#[Group('panel')]
#[Group('event')]
final class EventCellRendererTest extends TestCase
{
    public function testRenderClassCellOmitsNamespacePrefixForGlobalClasses(): void
    {
        $cell = EventCellRenderer::renderClassCell(self::makeRow(class: 'stdClass'));

        self::assertStringContainsString(
            '<strong>stdClass</strong>',
            $cell,
            'Short name must render bold.',
        );
        self::assertStringNotContainsString(
            'yii-debug-muted',
            $cell,
            'Global classes must not emit a namespace prefix.',
        );
    }

    public function testRenderClassCellSplitsFqcnIntoMutedNamespaceAndStrongShortName(): void
    {
        $cell = EventCellRenderer::renderClassCell(self::makeRow(class: 'yii\\base\\Event'));

        self::assertStringContainsString(
            'yii-debug-muted',
            $cell,
            'Namespace prefix must render muted.',
        );
        self::assertStringContainsString(
            '<strong>Event</strong>',
            $cell,
            'Short name must render bold.',
        );
        self::assertStringContainsString(
            'yii\base\Event',
            $cell,
            'Full FQCN must sit in the `title` attribute.',
        );
    }

    public function testRenderSenderCellRendersEmDashForStaticEvents(): void
    {
        self::assertSame(
            '—',
            EventCellRenderer::renderSenderCell(self::makeRow(senderClass: '')),
            'Empty sender must collapse to an em dash.',
        );
    }

    public function testRenderSenderCellSplitsFqcnIntoMutedNamespaceAndStrongShortName(): void
    {
        $cell = EventCellRenderer::renderSenderCell(self::makeRow(senderClass: 'yii\\web\\Application'));

        self::assertStringContainsString(
            'yii-debug-muted',
            $cell,
            'Namespace prefix must render muted.',
        );
        self::assertStringContainsString(
            '<strong>Application</strong>',
            $cell,
            'Short name must render bold.',
        );
    }

    public function testRenderStaticCellRendersEmDashForObjectEvents(): void
    {
        self::assertSame(
            '—',
            EventCellRenderer::renderStaticCell(self::makeRow(isStatic: '0')),
            'Object events must collapse to an em dash.',
        );
    }

    public function testRenderStaticCellRendersMutedBadgeForStaticEvents(): void
    {
        $cell = EventCellRenderer::renderStaticCell(self::makeRow(isStatic: '1'));

        self::assertStringContainsString(
            'yii-debug-badge-muted',
            $cell,
            'Static flag must render the muted badge.',
        );
        self::assertStringContainsString(
            'static',
            $cell,
            "Badge text must read 'static'.",
        );
    }

    public function testRenderTimeCellFormatsTimestampAsHmsWithMillis(): void
    {
        self::assertSame(
            date('H:i:s', 1_700_000_000) . '.789',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 1_700_000_000.789)),
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellHandlesZeroTime(): void
    {
        self::assertSame(
            date('H:i:s', 0) . '.000',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 0.0)),
            "Zero time must format as 'H:i:s.000'.",
        );
    }

    public function testRenderTimeCellKeepsMillisecondsBelowTheNextBoundary(): void
    {
        self::assertSame(
            date('H:i:s', 1) . '.500',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 1.5005)),
            'Sub-millisecond fractions must not advance the rendered millisecond value.',
        );
    }

    public function testRenderTimeCellPadsMillisecondsWithLeadingZeros(): void
    {
        self::assertSame(
            date('H:i:s', 1_700_000_000) . '.005',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 1_700_000_000.005)),
            'Milliseconds below 100 must be zero-padded to three digits.',
        );
    }

    private static function makeRow(
        float $time = 0.0,
        string $name = 'EVENT_X',
        string $class = 'yii\\base\\Event',
        string $isStatic = '0',
        string $senderClass = 'yii\\web\\Application',
    ): EventRow {
        return new EventRow(
            time: $time,
            name: $name,
            class: $class,
            isStatic: $isStatic,
            senderClass: $senderClass,
        );
    }
}
