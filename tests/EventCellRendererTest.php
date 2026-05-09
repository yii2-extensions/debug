<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\event\{EventCellRenderer, EventRow};

/**
 * Unit tests for {@see EventCellRenderer} covering the typed cell renderers used by the events grid (time formatting
 * and sender pass-through).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('event')]
final class EventCellRendererTest extends TestCase
{
    public function testRenderSenderCellReturnsEmptyStringForStaticEvents(): void
    {
        self::assertSame(
            '',
            EventCellRenderer::renderSenderCell(self::makeRow(senderClass: '')),
            'Static events must yield an empty sender cell.',
        );
    }

    public function testRenderSenderCellReturnsSenderClassAsIs(): void
    {
        self::assertSame(
            'yii\\web\\Application',
            EventCellRenderer::renderSenderCell(self::makeRow(senderClass: 'yii\\web\\Application')),
            'Sender FQCN must be returned verbatim.',
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
