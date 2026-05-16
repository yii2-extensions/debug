<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\sidebar;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\{ConfigPanel, RequestPanel};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\sidebar\SidebarDataNormalizer;

/**
 * Unit tests for {@see SidebarDataNormalizer} covering the narrowing of `_sidebar.php` inputs (panels + manifest +
 * activePanel + summary) into the typed view-model. Validates both 'fromView' and 'fromIndex' factories.
 */
#[Group('panel')]
#[Group('sidebar')]
final class SidebarDataNormalizerTest extends TestCase
{
    public function testFromIndexBuildsNavItemsForLatestTagWhenManifestNonEmpty(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromIndex(
            ['request' => $panel],
            ['tag-newest' => ['method' => 'GET']],
            '',
        );

        self::assertArrayHasKey(
            1,
            $view->navItems,
            'Panel entries must be appended after the History entry.',
        );

        $panelItem = $view->navItems[1];

        self::assertContains(
            'tag-newest',
            $panelItem->url,
            'Index-mode panel link must carry the latest tag.',
        );
        self::assertSame(
            'Open this panel on the latest request',
            $panelItem->tooltip,
            'Index-mode panel tooltip must invite picking the latest request.',
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
            ['index'],
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
        $view = SidebarDataNormalizer::fromIndex(
            [],
            [
                'tag-1' => [
                    'method' => 'GET',
                    'statusCode' => 200,
                ],
            ],
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
        $view = SidebarDataNormalizer::fromIndex(
            [],
            [
                'tag-1' => [
                    'method' => 'GET',
                    'statusCode' => 200,
                ],
            ],
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
            'Latest request',
            $view->snapshot->title,
            "Index mode must use the 'Latest request' heading.",
        );
    }

    public function testFromIndexSurfacesLatestTagAsSnapshot(): void
    {
        $manifest = [
            'tag-newest' => ['method' => 'GET', 'statusCode' => 200, 'url' => 'http://example.test/'],
            'tag-older' => ['method' => 'POST', 'statusCode' => 201, 'url' => 'http://example.test/x'],
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

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            [
                'tag-1' => [
                    'method' => 'GET',
                    'statusCode' => 200,
                ],
            ],
            $panel,
            'tag-1',
            [
                'method' => 'GET',
                'statusCode' => 200,
            ],
        );

        self::assertArrayHasKey(
            0,
            $view->navItems,
            'History must include at least one navigation item.',
        );
        self::assertContains(
            'tag-1',
            $view->navItems[0]->url,
            "History entry must carry the active tag as 'cursor'.",
        );
    }

    public function testFromViewLeavesTimeEmptyForNonPositiveOrNonNumericTimestamp(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => []],
            $panel,
            'tag-1',
            ['time' => 'not-a-number'],
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
            ['tag-1' => []],
            $panel,
            'tag-1',
            [
                'method' => 'GET',
                'statusCode' => 200,
                'url' => 'http://example.test/',
            ],
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertSame(
            'success',
            $view->snapshot->statusVariant,
            "Status '200' must map to the success variant.",
        );
    }

    public function testFromViewMarksSnapshotAsNonCursor(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => []],
            $panel,
            'tag-1',
            [
                'method' => 'GET',
                'statusCode' => 200,
            ],
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
    }

    public function testFromViewPopulatesPrevAndNextNavigatorsWhenSnapshotIsMiddleOfManifest(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $manifest = [
            'tag-newest' => ['method' => 'GET'],
            'tag-middle' => ['method' => 'GET'],
            'tag-oldest' => ['method' => 'GET'],
        ];

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            $manifest,
            $panel,
            'tag-middle',
            ['method' => 'GET'],
        );

        self::assertNotNull(
            $view->snapshot,
            'Snapshot must surface.',
        );
        self::assertTrue(
            $view->snapshot->hasPrev,
            'Middle-tag snapshot must expose a previous navigator.',
        );
        self::assertTrue(
            $view->snapshot->hasNext,
            'Middle-tag snapshot must expose a next navigator.',
        );
    }

    public function testFromViewRendersTimeChipWhenTimestampIsPositive(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $view = SidebarDataNormalizer::fromView(
            ['request' => $panel],
            ['tag-1' => []],
            $panel,
            'tag-1',
            ['time' => 1700000000],
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
            ['tag-1' => []],
            $panel,
            'tag-1',
            ['method' => 'GET', 'url' => 'http://:80/', 'statusCode' => 200],
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
            ['tag-1' => []],
            $request,
            'tag-1',
            [],
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
    }

    public function testStatusVariantMappingForKnownBuckets(): void
    {
        $this->mockWebApplication();

        $panel = new RequestPanel();

        $panel->id = 'request';

        $codes = [
            100 => 'muted',
            200 => 'success',
            304 => 'muted',
            404 => 'warning',
            500 => 'danger',
        ];

        foreach ($codes as $code => $expected) {
            $view = SidebarDataNormalizer::fromView(
                ['request' => $panel],
                ['tag-1' => []],
                $panel,
                'tag-1',
                ['statusCode' => $code],
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
            ['tag-1' => []],
            $panel,
            'tag-1',
            [
                'method' => 'GET',
                'url' => 'http://example.test:8080/foo?bar=1#baz',
                'statusCode' => 200,
            ],
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
