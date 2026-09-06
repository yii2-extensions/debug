<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPForge\Debug\Panel\Event\{EventInspection, EventRow, EventSnapshot};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use stdClass;
use Yii;
use yii\base\Event;
use yii\debug\panels\EventPanel;
use yii\debug\tests\provider\EventPanelProvider;
use yii\debug\tests\support\TestCase;

use function substr_count;

/**
 * Unit tests for {@see EventPanel} covering snapshot hydration, the toolbar count chip, and the rendered detail/summary
 * views.
 */
#[Group('panel')]
#[Group('event')]
final class EventPanelTest extends TestCase
{
    public function testEventsRemainReadableThroughTheYiiGetterContract(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $events = [new EventRow(1.0, 'afterSave', Event::class, '0', 'App')];

        $this->hydratePanel($panel, new EventSnapshot($events));

        self::assertTrue(
            $panel->canGetProperty('events'),
            'Yii must recognize the events getter.',
        );
        self::assertSame(
            $panel->getEvents(),
            $panel->__get('events'),
            'Magic property access must return the captured event rows.',
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoEventsCaptured(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->hydratePanel(
            $panel,
            new EventSnapshot([]),
        );

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

        $this->hydratePanel(
            $panel,
            new EventSnapshot(
                [
                    new EventRow(1.0, 'init', Event::class, '1', 'App'),
                    new EventRow(2.0, 'afterSave', Event::class, '0', 'App'),
                ],
            ),
        );

        self::assertStringContainsString(
            '<strong>1</strong> static',
            $panel->getDetail(),
            'Strip must count the static events.',
        );
    }

    public function testGetDetailRendersWithCapturedEvents(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        $this->hydratePanel(
            $panel,
            new EventSnapshot(
                [
                    new EventRow(1.0, 'afterSave', Event::class, '0', 'App'),
                ],
            ),
        );

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

    public function testGetDetailScopesEverySummaryMetricToFilteredEvents(): void
    {
        $panel = $this->makePanel(EventPanel::class);

        Yii::$app->getRequest()->setQueryParams(
            [
                'Event' => ['name' => 'afterSave'],
            ],
        );

        $this->hydratePanel(
            $panel,
            new EventSnapshot(
                [
                    new EventRow(1.0, 'init', stdClass::class, '1', ''),
                    new EventRow(2.0, 'afterSave', Event::class, '0', 'App'),
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            '<strong>1</strong> events',
            $detail,
            'Event count must report the filtered set.',
        );
        self::assertStringContainsString(
            '<strong>1</strong> classes',
            $detail,
            'Distinct-class count must use the same filtered set as the event count.',
        );
        self::assertStringNotContainsString(
            '<strong>1</strong> static',
            $detail,
            'Static-event count must exclude captured rows removed by the active filter.',
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

        $this->hydratePanel(
            $panel,
            new EventSnapshot(
                [
                    new EventRow(1.0, 'a', Event::class, '0', ''),
                    new EventRow(2.0, 'b', Event::class, '0', ''),
                ],
            ),
        );

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

    /**
     * @param array<string, mixed> $query
     */
    #[DataProviderExternal(EventPanelProvider::class, 'queryControls')]
    public function testUnifiedTablePreservesObservationIdentity(array $query, int $index, string $offset, string $gap): void
    {
        $inspection = (new EventInspection())
            ->withContext(['Action' => '<diagnostic>'], 'captured');
        $snapshot = new EventSnapshot(
            [
                new EventRow(10.0, 'first', 'Event', '0', 'Worker'),
                (new EventRow(10.125, 'second', 'Event', '0', 'Worker'))->withInspection($inspection),
                (new EventRow(10.5, 'third', 'Event', '0', 'Worker'))->withInspection($inspection),
            ],
        );

        $panel = $this->makePanel(EventPanel::class);

        Yii::$app->getRequest()->setQueryParams($query);

        $this->hydratePanel($panel, $snapshot);

        $html = $panel->getDetail();

        self::assertSame(
            1,
            substr_count($html, '<table'),
            'Events must render exactly one table.',
        );
        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-event-item"'),
            'Each visible event must appear once.',
        );
        self::assertSame(
            1,
            substr_count($html, "id=\"event-{$index}\""),
            'Pagination and sorting must preserve the original observation identity.',
        );
        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-event-detail-row"'),
            'Each visible event must have one companion diagnostic row.',
        );
        self::assertStringContainsString(
            'colspan="6"',
            $html,
            'Diagnostics must span all event columns.',
        );
        self::assertStringContainsString(
            "aria-controls=\"event-{$index}-detail\"",
            $html,
            'The disclosure must identify its companion diagnostics.',
        );
        self::assertSame(
            1,
            substr_count($html, '&lt;diagnostic&gt;'),
            'Captured context must not be duplicated in the event summary.',
        );
        self::assertStringContainsString(
            $offset,
            $html,
            'The visible offset must refer to the original capture.',
        );
        self::assertStringContainsString(
            $gap,
            $html,
            'The visible gap must refer to the previous original observation.',
        );
        self::assertStringContainsString(
            '&lt;diagnostic&gt;',
            $html,
            'Inline diagnostics must preserve escaped captured context.',
        );
        self::assertStringContainsString(
            'name="Event[class]"',
            $html,
            'Column filters must remain available.',
        );
        self::assertStringNotContainsString(
            'yii-debug-event-raw',
            $html,
            'The secondary event table must be removed.',
        );
        self::assertStringNotContainsString(
            'Execution flow',
            $html,
            'Sorted results must not be labeled as chronological execution flow.',
        );
    }
}
