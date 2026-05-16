<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use yii\base\{Component, Event};
use yii\debug\panels\EventPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventPanel} covering the wildcard event capture, the saved-payload narrowing, the toolbar count
 * chip, and the rendered detail/summary views.
 */
#[Group('panel')]
#[Group('event')]
final class EventPanelTest extends TestCase
{
    public function testGetDetailRendersWithCapturedEvents(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $panel->data = [
            ['time' => 1.0, 'name' => 'afterSave', 'class' => Event::class, 'isStatic' => '0', 'senderClass' => 'App'],
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            'Events',
            $panel->getName(),
            "Display name must be 'Events'.",
        );
        self::assertSame(
            'events',
            $panel->getToolbarIcon(),
            "Icon key must be 'events'.",
        );
    }

    public function testGetSummaryRendersChipWhenEventsPresent(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $panel->data = [
            ['time' => 1.0, 'name' => 'a', 'class' => Event::class, 'isStatic' => '0', 'senderClass' => ''],
        ];

        self::assertStringContainsString(
            'Events',
            $panel->getSummary(),
            'Chip must render the panel label.',
        );
    }

    public function testGetSummaryReturnsEmptyMarkupWhenNoEvents(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            '',
            trim($panel->getSummary()),
            'No data means no toolbar chip.',
        );
    }

    public function testGetToolbarItemsEmitsCountChipWhenEventsPresent(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $panel->data = [
            ['time' => 1.0, 'name' => 'a', 'class' => Event::class, 'isStatic' => '0', 'senderClass' => ''],
            ['time' => 2.0, 'name' => 'b', 'class' => Event::class, 'isStatic' => '0', 'senderClass' => ''],
        ];

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            2,
            $first['value'] ?? null,
            'Chip value must match the event count.',
        );
    }

    public function testGetToolbarItemsReturnsNullWhenDataIsCorrupt(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        self::assertNull(
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Non-array data must skip the toolbar item.',
        );
    }

    public function testGetToolbarItemsReturnsNullWhenEventsAreEmpty(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertNull(
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Empty data must skip the toolbar item.',
        );
    }

    public function testInitCapturesEventsFiredByWildcardListener(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $sender = new Component();

        $sender->trigger('test.event');

        $saved = $panel->save();

        $captured = $saved[0] ?? self::fail('Expected one captured event.');

        self::assertSame(
            'test.event',
            $captured['name'],
            'Captured `name` must match the trigger.',
        );
        self::assertSame(
            Component::class,
            $captured['senderClass'],
            'Captured `senderClass` must match the sender FQCN.',
        );
        self::assertSame(
            '0',
            $captured['isStatic'],
            "Object sender must mark 'isStatic' as '0'.",
        );

        Event::offAll();
    }

    public function testInitMarksStaticEventsWithSenderClassFromString(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $event = new Event();

        $this->setInaccessibleProperty(
            $event,
            'sender',
            stdClass::class,
        );

        Event::trigger(
            stdClass::class,
            'static.event',
            $event,
        );

        $saved = $panel->save();

        $captured = $saved[0] ?? self::fail('Expected one captured event.');

        self::assertSame(
            '1',
            $captured['isStatic'],
            "Static event must mark 'isStatic' as '1'.",
        );
        self::assertSame(
            stdClass::class,
            $captured['senderClass'],
            'Class-level sender must round-trip as a string.',
        );

        Event::offAll();
    }

    public function testNormalizeEventsDropsNonArrayEntriesAndNonStringKeys(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            [
                ['name' => 'valid', 0 => 'dropped-int-key'],
                'invalid-entry',
                ['name' => 'kept'],
            ],
        );

        $normalized = $this->invoke($panel, 'normalizeEvents', [$panel->data]);

        self::assertIsArray(
            $normalized,
            'Normalized must be an array.',
        );
        self::assertCount(
            2,
            $normalized,
            'Non-array entries must be dropped.',
        );

        $first = $normalized[0] ?? self::fail('Expected one row.');

        self::assertIsArray(
            $first,
            'Row must be an array.',
        );
        self::assertArrayNotHasKey(
            0,
            $first,
            'Int keys must be filtered out.',
        );
    }

    public function testNormalizeEventsReturnsEmptyForNonArrayInput(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            [],
            $this->invoke($panel, 'normalizeEvents', ['not-an-array']),
            'Non-array input must collapse to `[]`.',
        );
    }

    public function testSaveReturnsEventsCapturedSinceInit(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            [],
            $panel->save(),
            'No fired events means an empty payload.',
        );

        Event::offAll();
    }
}
