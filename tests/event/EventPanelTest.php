<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPUnit\Framework\Attributes\Group;
use stdClass;
use yii\base\{Component, Event};
use yii\debug\panels\event\{EventRow, EventSnapshot};
use yii\debug\panels\EventPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventPanel} covering the wildcard event capture, snapshot hydration, the toolbar count
 * chip, and the rendered detail/summary views.
 */
#[Group('panel')]
#[Group('event')]
final class EventPanelTest extends TestCase
{
    public function testCaptureReturnsEventsCapturedSinceInit(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            [],
            $panel->capture()->entries(),
            'No fired events means an empty payload.',
        );

        Event::offAll();
    }

    public function testGetDetailRendersEmptyStateWhenNoEventsCaptured(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->hydratePanel($panel, new EventSnapshot([]));

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $detail,
            'Empty capture must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No events triggered in this request',
            $detail,
            'Empty-state heading must explain the missing events.',
        );
    }

    public function testGetDetailRendersStaticCountStatWhenStaticEventsPresent(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->hydratePanel($panel, new EventSnapshot([
            new EventRow(1.0, 'init', Event::class, '1', 'App'),
            new EventRow(2.0, 'afterSave', Event::class, '0', 'App'),
        ]));

        self::assertStringContainsString(
            '<strong>1</strong> static',
            $panel->getDetail(),
            'Strip must count the static events.',
        );
    }

    public function testGetDetailRendersWithCapturedEvents(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->hydratePanel($panel, new EventSnapshot([
            new EventRow(1.0, 'afterSave', Event::class, '0', 'App'),
        ]));

        $detail = $panel->getDetail();

        self::assertNotEmpty(
            $detail,
            'Detail view must produce markup.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-event',
            $detail,
            'Grid must carry the event variant class.',
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

    public function testGetToolbarItemsEmitsCountChipWhenEventsPresent(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->hydratePanel($panel, new EventSnapshot([
            new EventRow(1.0, 'a', Event::class, '0', ''),
            new EventRow(2.0, 'b', Event::class, '0', ''),
        ]));

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

    public function testGetToolbarItemsReturnsEmptyArrayWhenDataIsCorrupt(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Non-array data must skip the toolbar item.',
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenEventsAreEmpty(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        self::assertSame(
            [],
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

        $saved = $panel->capture()->entries();

        $captured = $saved[0] ?? self::fail('Expected one captured event.');

        self::assertSame('test.event', $captured->name, 'Captured `name` must match the trigger.');
        self::assertSame(
            Component::class,
            $captured->senderClass,
            'Captured `senderClass` must match the sender FQCN.',
        );
        self::assertSame(
            '0',
            $captured->isStatic,
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

        $saved = $panel->capture()->entries();

        $captured = $saved[0] ?? self::fail('Expected one captured event.');

        self::assertSame(
            '1',
            $captured->isStatic,
            "Static event must mark 'isStatic' as '1'.",
        );
        self::assertSame(
            stdClass::class,
            $captured->senderClass,
            'Class-level sender must round-trip as a string.',
        );

        Event::offAll();
    }
}
