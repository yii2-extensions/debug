<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPUnit\Framework\Attributes\Group;
use yii\base\Event;
use yii\debug\panels\event\EventRow;
use yii\debug\storage\HydrationException;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventRow} covering the strict JSON hydration and the summary-strip aggregates.
 *
 * @since 0.2
 */
#[Group('panel')]
#[Group('event')]
final class EventRowTest extends TestCase
{
    public function testDistinctClassCountCountsUniqueClassNames(): void
    {
        $count = EventRow::distinctClassCount(
            [
                self::row(class: Event::class),
                self::row(class: Event::class),
                self::row(class: 'yii\\web\\Application'),
                self::row(class: ''),
            ],
        );

        self::assertSame(2, $count, 'Duplicates and empty classes must not inflate the count.');
    }

    public function testDistinctClassCountReturnsZeroForEmptyList(): void
    {
        self::assertSame(0, EventRow::distinctClassCount([]), 'An empty capture has no distinct classes.');
    }

    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = self::row(time: 1_700_000_000.5, name: 'afterSave', class: Event::class, isStatic: '1');

        self::assertEquals(
            $row,
            EventRow::fromArray($row->jsonSerialize(), '$.panels.event.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testStaticCountCountsOnlyStaticallyTriggeredRows(): void
    {
        $count = EventRow::staticCount([self::row(isStatic: '1'), self::row(), self::row(isStatic: '1')]);

        self::assertSame(2, $count, 'Only rows flagged `1` are static.');
    }

    public function testThrowHydrationExceptionWhenTimeIsANumericString(): void
    {
        $this->expectException(HydrationException::class);

        EventRow::fromArray(
            [
                'time' => '1.0',
                'name' => 'init',
                'class' => Event::class,
                'isStatic' => '0',
                'senderClass' => 'App',
            ],
            '$.panels.event.entries[0]',
        );
    }

    private static function row(
        float $time = 1.0,
        string $name = 'init',
        string $class = Event::class,
        string $isStatic = '0',
        string $senderClass = 'App',
    ): EventRow {
        return new EventRow($time, $name, $class, $isStatic, $senderClass);
    }
}
