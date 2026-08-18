<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\sidebar;

use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\{ConfigPanel, InertiaPanel, RequestPanel};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\sidebar\{SidebarDataNormalizer, SidebarNavItem};

use function array_map;

/**
 * Unit tests for {@see SidebarDataNormalizer} covering panel, manifest, and summary narrowing into the typed
 * view-model through the `fromView()` and `fromIndex()` factories.
 */
#[Group('panel')]
#[Group('sidebar')]
final class SidebarDataNormalizerTest extends TestCase
{
    public function testConsoleInvocationUrlIsShownVerbatim(): void
    {
        $this->mockWebApplication();

        $panel = $this->makePanel(RequestPanel::class);
        $summary = $this->requestSummary('tag-1', ['url' => 'php yii migrate/up']);

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => $summary],
            $panel,
            'tag-1',
            $summary,
        );

        $snapshot = $view->snapshot ?? self::fail('The view must carry a snapshot.');

        self::assertSame(
            'php yii migrate/up',
            $snapshot->path,
            'A console invocation has no host portion to strip.',
        );
    }

    public function testFromIndexBuildsNavItemsForNewestTagWhenManifestNonEmpty(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromIndex(
            ['request' => $panel],
            ['tag-newest' => $this->requestSummary('tag-newest')],
            '',
        );

        self::assertArrayHasKey(
            1,
            $view->navItems,
            'Panel entries must be appended after the History entry.',
        );

        $panelItem = $view->navItems[1];

        self::assertSame(
            ['/debug/view', 'tag' => 'tag-newest', 'panel' => 'request'],
            $panelItem->url,
            'Index-mode panel link must carry its route, newest tag, and panel ID.',
        );
        self::assertStringContainsString(
            '<svg',
            $panelItem->iconSvg,
            'A panel toolbar icon must be rendered into the navigation item.',
        );
        self::assertSame(
            'Open this panel on the newest request',
            $panelItem->tooltip,
            'Index-mode panel tooltip must invite picking the newest request.',
        );
    }

    public function testFromIndexBuildsNavItemsWithIndexFallbackWhenManifestEmpty(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromIndex(
            ['request' => $panel],
            [],
            '',
        );

        self::assertArrayHasKey(
            1,
            $view->navItems,
            'Panel nav entry must follow the History entry even with empty manifest.',
        );

        $panelItem = $view->navItems[1];

        self::assertSame(
            ['/debug/index'],
            $panelItem->url,
            "Empty manifest must drop panel entries back to the 'index' route.",
        );
        self::assertSame(
            'Pick a request first',
            $panelItem->tooltip,
            "Empty manifest must use the 'pick a request' tooltip.",
        );
    }

    public function testFromIndexDropsSnapshotWhenManifestIsEmpty(): void
    {
        $this->mockWebApplication();

        $view = SidebarDataNormalizer::fromIndex(
            [],
            [],
            '',
        );

        self::assertNull(
            $view->snapshot,
            'Empty manifest must skip the snapshot card.',
        );
    }

    public function testFromIndexHighlightsHistoryNavEntry(): void
    {
        $this->mockWebApplication();

        $view = SidebarDataNormalizer::fromIndex(
            [],
            ['tag-1' => $this->requestSummary()],
            '',
        );

        self::assertNotEmpty(
            $view->navItems,
            'History entry must always be present.',
        );
        self::assertSame(
            'History',
            $view->navItems[0]->label,
            'History entry must come first.',
        );
        self::assertTrue(
            $view->navItems[0]->isActive,
            'History nav must be active in index mode.',
        );
    }

    public function testFromIndexMarksSnapshotAsCursor(): void
    {
        $this->mockWebApplication();

        $view = SidebarDataNormalizer::fromIndex(
            [],
            ['tag-1' => $this->requestSummary()],
            'init-tag',
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface when manifest has entries.',
        );
        self::assertTrue(
            $view->snapshot->isCursor,
            'Index mode must mark the snapshot as cursor.',
        );
        self::assertSame(
            'init-tag',
            $view->snapshot->cursorInitTag,
            "'cursorInit' must surface on the DTO.",
        );
        self::assertSame(
            'Newest request',
            $view->snapshot->title,
            "Index mode must use the 'Newest request' heading.",
        );
        self::assertSame(
            'Newest captured request',
            $view->snapshot->ariaLabel,
            'Index mode must expose the expanded snapshot aria-label.',
        );
        self::assertSame(
            ['/debug/view', 'tag' => 'tag-1'],
            $view->snapshot->newestUrl,
            'A navigator without panels must omit the panel parameter.',
        );
        self::assertSame(
            ['/debug/view', 'tag' => 'tag-1'],
            $view->snapshot->oldestUrl,
            'The single snapshot must be both the newest and oldest destination.',
        );
        self::assertSame(
            [],
            $view->snapshot->newerUrl,
            'The newest snapshot must not expose a newer destination.',
        );
        self::assertSame(
            [],
            $view->snapshot->olderUrl,
            'The oldest snapshot must not expose an older destination.',
        );
        self::assertTrue(
            $view->snapshot->isNewest,
            'A single snapshot must be marked as newest.',
        );
        self::assertTrue(
            $view->snapshot->isOldest,
            'A single snapshot must be marked as oldest.',
        );
    }

    public function testFromIndexSurfacesNewestTagAsSnapshot(): void
    {
        $this->mockWebApplication();

        $manifest = [
            'tag-newest' => $this->requestSummary('tag-newest', ['url' => 'http://example.test/']),
            'tag-older' => $this->requestSummary(
                'tag-older',
                ['method' => 'POST', 'statusCode' => 201, 'url' => 'http://example.test/x'],
            ),
        ];

        $view = SidebarDataNormalizer::fromIndex(
            [],
            $manifest,
            '',
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertSame(
            'GET',
            $view->snapshot->method,
            'Snapshot must reflect the newest manifest entry.',
        );
        self::assertSame(
            200,
            $view->snapshot->statusCode,
            'Status must come from the newest entry.',
        );
    }

    public function testFromViewBuildsHistoryUrlWithCursorParam(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $inactivePanel = new RequestPanel();

        $inactivePanel->id = 'other';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel, 'other' => $inactivePanel],
            ['tag-1' => $this->requestSummary()],
            $panel,
            'tag-1',
            $this->requestSummary(),
        );

        self::assertArrayHasKey(
            0,
            $view->navItems,
            'History must include at least one navigation item.',
        );
        self::assertSame(
            ['/debug/index', 'cursor' => 'tag-1'],
            $view->navItems[0]->url,
            "History entry must carry the active tag as the exact 'cursor' route.",
        );

        $panelItem = $view->navItems[1] ?? self::fail('Request panel navigation item must surface.');

        self::assertSame(
            ['/debug/view', 'tag' => 'tag-1', 'panel' => 'request'],
            $panelItem->url,
            'View-mode panel link must preserve the active capture and panel.',
        );
        self::assertSame(
            'Request',
            $panelItem->tooltip,
            'View-mode panel tooltip must use the panel name.',
        );
        self::assertTrue(
            $panelItem->isActive,
            'View mode must mark the selected panel as active.',
        );

        $inactiveItem = $view->navItems[2] ?? self::fail('Inactive panel navigation item must surface.');

        self::assertFalse(
            $inactiveItem->isActive,
            'View mode must not mark other panels as active.',
        );
    }

    public function testFromViewLeavesTimeEmptyForNonPositiveOrNonNumericTimestamp(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => $this->requestSummary('tag-1', ['time' => 0.0])],
            $panel,
            'tag-1',
            $this->requestSummary('tag-1', ['time' => 0.0]),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertSame(
            '',
            $view->snapshot->time,
            'Non-numeric time must collapse to the empty string.',
        );
    }

    public function testFromViewMapsStatusCodeToSuccessVariant(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => $this->requestSummary()],
            $panel,
            'tag-1',
            $this->requestSummary('tag-1', ['url' => 'http://example.test/']),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertSame(
            '2xx',
            $view->snapshot->statusVariant,
            "Status '200' must map to the '2xx' status class.",
        );
    }

    public function testFromViewMarksSnapshotAsNonCursor(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => $this->requestSummary()],
            $panel,
            'tag-1',
            $this->requestSummary(),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertFalse(
            $view->snapshot->isCursor,
            'View mode must NOT mark the snapshot as cursor.',
        );
        self::assertSame(
            'Current request',
            $view->snapshot->title,
            "View mode must use the 'Current request' heading.",
        );
        self::assertSame(
            'Current request',
            $view->snapshot->ariaLabel,
            "View mode must use the 'Current request' aria-label.",
        );
    }

    public function testFromViewOmitsTagFromBoundaryUrlsWhenManifestIsEmpty(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            [],
            $panel,
            'tag-1',
            $this->requestSummary(),
        );

        $snapshot = $view->snapshot ?? self::fail('View mode must carry the supplied snapshot.');

        self::assertSame(
            ['/debug/view', 'panel' => 'request'],
            $snapshot->newestUrl,
            'An empty manifest must omit the missing newest tag.',
        );
        self::assertSame(
            ['/debug/view', 'panel' => 'request'],
            $snapshot->oldestUrl,
            'An empty manifest must omit the missing oldest tag.',
        );
    }

    public function testFromViewPopulatesPrevAndNextNavigatorsWhenSnapshotIsMiddleOfManifest(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $manifest = [
            'tag-newest' => $this->requestSummary('tag-newest'),
            'tag-middle' => $this->requestSummary('tag-middle'),
            'tag-oldest' => $this->requestSummary('tag-oldest'),
        ];

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            $manifest,
            $panel,
            'tag-middle',
            $this->requestSummary('tag-middle'),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertTrue(
            $view->snapshot->hasNewer,
            'Middle-tag snapshot must expose a newer navigator.',
        );
        self::assertTrue(
            $view->snapshot->hasOlder,
            'Middle-tag snapshot must expose a older navigator.',
        );
        self::assertSame(
            ['/debug/view', 'tag' => 'tag-newest', 'panel' => 'request'],
            $view->snapshot->newerUrl,
            'Middle-tag snapshot must link to the immediately newer capture.',
        );
        self::assertSame(
            ['/debug/view', 'tag' => 'tag-oldest', 'panel' => 'request'],
            $view->snapshot->olderUrl,
            'Middle-tag snapshot must link to the immediately older capture.',
        );
        self::assertFalse(
            $view->snapshot->isNewest,
            'Middle-tag snapshot must not be marked as newest.',
        );
        self::assertFalse(
            $view->snapshot->isOldest,
            'Middle-tag snapshot must not be marked as oldest.',
        );
    }

    public function testFromViewRendersTimeChipWhenTimestampIsPositive(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => $this->requestSummary()],
            $panel,
            'tag-1',
            $this->requestSummary('tag-1', ['time' => 1_700_000_000.0]),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertNotSame(
            '',
            $view->snapshot->time,
            'Positive numeric time must render as a formatted clock string.',
        );
    }

    public function testFromViewReturnsFullUrlWhenParseUrlFails(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => $this->requestSummary('tag-1', ['url' => 'http://:80/'])],
            $panel,
            'tag-1',
            $this->requestSummary('tag-1', ['url' => 'http://:80/']),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertSame(
            'http://:80/',
            $view->snapshot->path,
            'Unparseable URL must pass through verbatim.',
        );
    }

    public function testFromViewSkipsConfigPanelInNavItems(): void
    {
        $this->mockWebApplication();

        $request = new RequestPanel();

        $request->id = 'request';

        $config = new ConfigPanel();

        $config->id = 'config';

        $view = SidebarDataNormalizer::fromView(
            ['config' => $config, 'request' => $request],
            ['tag-1' => $this->requestSummary()],
            $request,
            'tag-1',
            $this->requestSummary(),
        );

        $ids = [];

        foreach ($view->navItems as $item) {
            $ids[] = $item->label;
        }

        self::assertNotContains(
            'Configuration',
            $ids,
            'Config panel must be skipped in the sidebar nav.',
        );
        self::assertContains(
            'Request',
            $ids,
            'Panels after Configuration must remain available.',
        );
    }

    public function testFromViewSkipsPanelsWithoutContentForTheCapture(): void
    {
        $this->mockWebApplication();

        $active = new RequestPanel();
        $active->id = 'request';

        $inertia = new InertiaPanel();
        $inertia->id = 'inertia';
        $this->hydratePanel($inertia, InertiaSnapshot::capture(null, null, [], [], 200));

        $view = SidebarDataNormalizer::fromView(
            ['inertia' => $inertia, 'request' => $active],
            ['tag-1' => $this->requestSummary()],
            $active,
            'tag-1',
            $this->requestSummary(),
        );

        $labels = array_map(static fn(SidebarNavItem $item): string => $item->label, $view->navItems);

        self::assertContains(
            'Request',
            $labels,
            'Panels with content must stay listed.',
        );
        self::assertNotContains(
            'Inertia',
            $labels,
            'Content-less panels must be skipped in view mode.',
        );
    }

    public function testStatusVariantMappingForKnownBuckets(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $codes = [
            100 => 'none',
            200 => '2xx',
            304 => '3xx',
            404 => '4xx',
            500 => '5xx',
        ];

        foreach ($codes as $code => $expected) {
            $view = SidebarDataNormalizer::fromView(
                ['request' => $panel],
                ['tag-1' => $this->requestSummary('tag-1', ['statusCode' => $code])],
                $panel,
                'tag-1',
                $this->requestSummary('tag-1', ['statusCode' => $code]),
            );

            self::assertNotNull(
                $view->snapshot,
                "Snapshot must surface for status code {$code}.",
            );
            self::assertSame(
                $expected,
                $view->snapshot->statusVariant,
                "Status {$code} must map to {$expected} variant.",
            );
        }
    }

    public function testUrlPathStripsSchemeAndHost(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            [
                'tag-1' => $this->requestSummary(
                    'tag-1',
                    ['url' => 'http://example.test:8080/foo?bar=1#baz'],
                ),
            ],
            $panel,
            'tag-1',
            $this->requestSummary('tag-1', ['url' => 'http://example.test:8080/foo?bar=1#baz']),
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertSame(
            '/foo?bar=1#baz',
            $view->snapshot->path,
            "Snapshot path must drop 'scheme/host/port'.",
        );
    }
}
