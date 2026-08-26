<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\sidebar;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\sidebar\{SidebarNavItem, SidebarRenderer, SidebarSnapshot, SidebarView};

/**
 * Unit tests for {@see SidebarRenderer} covering the snapshot card composition (method/URL/status/time/AJAX), the
 * cursor-mode vs navigation-mode branching of the navigator row, and the panel nav entry rendering.
 */
#[Group('panel')]
#[Group('sidebar')]
final class SidebarRendererTest extends TestCase
{
    public function testRenderDelegatesLabeledNavigationGroupsToDebugCore(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [new SidebarNavItem('History', '', ['/debug/index'], 'History', false)],
            navGroups: [
                'Extensions' => [
                    new SidebarNavItem('Inertia', '', ['/debug/view', 'panel' => 'inertia'], 'Inertia', false),
                    new SidebarNavItem('Vite', '', ['/debug/view', 'panel' => 'vite'], 'Vite', true),
                ],
            ],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'yii-debug-side-section yii-debug-nav-group',
            $html,
            'The extension group must use the shared sidebar card.',
        );
        self::assertStringContainsString(
            'yii-debug-side-section-title',
            $html,
            'The extension title must render inside its card.',
        );
        self::assertStringContainsString(
            'Extensions',
            $html,
            'The extension group must keep its visible label.',
        );
        self::assertStringContainsString(
            'panel=vite',
            $html,
            'Grouped Yii route arrays must still resolve to URLs.',
        );
    }

    public function testRenderEmitsAriaCurrentOnActiveNavLink(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [
                new SidebarNavItem(
                    label: 'History',
                    iconSvg: '',
                    url: ['/debug/index'],
                    tooltip: 'History',
                    isActive: true,
                ),
                new SidebarNavItem(
                    label: 'Request',
                    iconSvg: '',
                    url: ['/debug/view', 'panel' => 'request'],
                    tooltip: 'Request',
                    isActive: false,
                ),
            ],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'aria-current="page"',
            $html,
            'Active nav entry must carry aria-current=page.',
        );
        self::assertStringContainsString(
            'is-active',
            $html,
            'Active nav entry must carry the is-active modifier.',
        );
        self::assertSame(
            1,
            substr_count($html, 'is-active'),
            'Only the active entry carries the modifier.',
        );
        self::assertMatchesRegularExpression(
            '/<a class="yii-debug-nav-link is-active"[^>]*title="History"[^>]*aria-current="page">/',
            $html,
            'The active link must preserve its base class, tooltip, and current-page marker.',
        );
    }

    public function testRenderEmitsCursorButtonsWhenSnapshotIsCursor(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(isCursor: true),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'data-yii-debug-cursor="newest"',
            $html,
            'Cursor mode must emit the Newest cursor button.',
        );
        self::assertStringContainsString(
            'data-yii-debug-cursor="older"',
            $html,
            'Cursor mode must emit the Older cursor button.',
        );
        self::assertStringContainsString(
            '<button',
            $html,
            'Cursor mode must use buttons instead of anchors.',
        );
    }

    public function testRenderEmitsHistoryCursorMarkerWhenSnapshotIsCursor(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(isCursor: true, cursorInitTag: 'init-tag'),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'data-yii-debug-history-cursor="true"',
            $html,
            'Cursor mode must emit a true history-cursor marker.',
        );
        self::assertStringContainsString(
            'data-yii-debug-cursor-init="init-tag"',
            $html,
            'Cursor init tag must surface as data attribute.',
        );
    }

    public function testRenderEmitsIconSpanWhenNavItemDeclaresIconSvg(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [
                new SidebarNavItem(
                    label: 'Request',
                    iconSvg: '<svg data-test="request-icon"></svg>',
                    url: ['/debug/view', 'panel' => 'request'],
                    tooltip: 'Request',
                    isActive: false,
                ),
            ],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'yii-debug-nav-link-icon',
            $html,
            'Nav item with iconSvg must wrap the markup in the icon span.',
        );
        self::assertStringContainsString(
            'data-test="request-icon"',
            $html,
            'Icon SVG payload must surface inside the nav link.',
        );
        self::assertStringContainsString(
            'aria-hidden="true"',
            $html,
            'Decorative panel icons must remain hidden from assistive technology.',
        );
    }

    public function testRenderHidesAjaxTagWhenNotAjax(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(isAjax: false),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertMatchesRegularExpression(
            '/yii-debug-snapshot-tag[^>]*hidden/',
            $html,
            'Non-AJAX snapshot must hide the AJAX tag.',
        );
    }

    public function testRenderHidesTimeChipWhenTimeEmpty(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(time: ''),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertMatchesRegularExpression(
            '/yii-debug-snapshot-time[^>]*hidden/',
            $html,
            'Empty time must hide the time chip.',
        );
    }

    public function testRenderShowsDashWhenStatusCodeIsZero(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(statusCode: 0),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            '>–<',
            $html,
            'Status 0 must surface as an en-dash placeholder.',
        );
    }

    public function testRenderSkipsSnapshotSectionWhenSnapshotIsNull(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringNotContainsString(
            'yii-debug-side-section',
            $html,
            'Null snapshot must skip the section entirely.',
        );
    }

    public function testRenderTintsSnapshotMethodAndStatusWithVocabularyClasses(): void
    {
        $html = SidebarRenderer::render(
            new SidebarView(
                snapshot: $this->snapshot(),
                navItems: [],
            ),
        );

        self::assertStringContainsString(
            'class="yii-debug-snapshot-method yii-debug-verb-get"',
            $html,
            "GET must wear the 'get' verb class.",
        );
        self::assertStringContainsString(
            'class="yii-debug-snapshot-status yii-debug-status-2xx"',
            $html,
            "Status '200' must wear the '2xx' status class.",
        );
        self::assertStringContainsString(
            'class="yii-debug-snapshot-status yii-debug-status-5xx"',
            SidebarRenderer::render(
                new SidebarView(
                    snapshot: $this->snapshot(statusCode: 500),
                    navItems: [],
                ),
            ),
            "Status '500' must wear the '5xx' status class.",
        );
    }

    public function testRenderWiresNavigationAnchorsInViewMode(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(isCursor: false),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'aria-label="Newest captured request"',
            $html,
            "Navigation mode must use the long 'aria-label' for Newest.",
        );
        self::assertStringContainsString(
            'title="GET http://example.test/index.php"',
            $html,
            'Snapshot tooltip must prefix the URL with the request method.',
        );
        self::assertMatchesRegularExpression(
            '/<button class="[^"]*is-disabled"[^>]*aria-label="Newer captured request">/',
            $html,
            'A missing newer capture must render a disabled button.',
        );
        self::assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*tag=older"[^>]*aria-label="Older captured request">/',
            $html,
            'An available older capture must render an anchor to its tag.',
        );
        self::assertStringNotContainsString(
            'data-yii-debug-cursor=',
            $html,
            'Navigation mode must NOT emit cursor buttons.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    private function snapshot(
        bool $isCursor = false,
        bool $isAjax = true,
        int $statusCode = 200,
        string $time = '12:34:56',
        string $cursorInitTag = '',
    ): SidebarSnapshot {
        return new SidebarSnapshot(
            title: $isCursor ? 'Newest request' : 'Current request',
            ariaLabel: $isCursor ? 'Newest captured request' : 'Current request',
            method: 'GET',
            path: '/index.php',
            fullUrl: 'http://example.test/index.php',
            statusCode: $statusCode,
            statusVariant: $statusCode >= 500 ? '5xx' : '2xx',
            time: $time,
            isAjax: $isAjax,
            isCursor: $isCursor,
            cursorInitTag: $cursorInitTag,
            newestUrl: ['/debug/view'],
            oldestUrl: ['/debug/view', 'tag' => 'oldest'],
            newerUrl: [],
            olderUrl: ['/debug/view', 'tag' => 'older'],
            isNewest: true,
            isOldest: false,
            hasNewer: false,
            hasOlder: true,
        );
    }
}
