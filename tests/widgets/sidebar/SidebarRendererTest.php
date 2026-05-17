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
    public function testRenderEmitsAriaCurrentOnActiveNavLink(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [
                new SidebarNavItem(
                    label: 'History',
                    iconSvg: '',
                    url: ['/debug/default/index'],
                    tooltip: 'History',
                    isActive: true,
                ),
                new SidebarNavItem(
                    label: 'Request',
                    iconSvg: '',
                    url: ['/debug/default/view', 'panel' => 'request'],
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
        self::assertStringContainsString(
            'yii-debug-nav-link-muted',
            $html,
            'Non-active nav entry must carry the muted modifier.',
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
            'data-yii-debug-cursor="first"',
            $html,
            'Cursor mode must emit the First cursor button.',
        );
        self::assertStringContainsString(
            'data-yii-debug-cursor="next"',
            $html,
            'Cursor mode must emit the Next cursor button.',
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
            'data-yii-debug-history-cursor',
            $html,
            'Cursor mode must emit the history-cursor marker.',
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
                    url: ['/debug/default/view', 'panel' => 'request'],
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

    public function testRenderWiresNavigationAnchorsInViewMode(): void
    {
        $view = new SidebarView(
            snapshot: $this->snapshot(isCursor: false),
            navItems: [],
        );

        $html = SidebarRenderer::render($view);

        self::assertStringContainsString(
            'aria-label="First captured request"',
            $html,
            "Navigation mode must use the long 'aria-label' for First.",
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
            title: $isCursor ? 'Latest request' : 'Current request',
            ariaLabel: $isCursor ? 'Latest captured request' : 'Current request',
            method: 'GET',
            path: '/index.php',
            fullUrl: 'http://example.test/index.php',
            statusCode: $statusCode,
            statusVariant: $statusCode >= 500 ? 'danger' : 'success',
            time: $time,
            isAjax: $isAjax,
            isCursor: $isCursor,
            cursorInitTag: $cursorInitTag,
            firstUrl: ['/debug/default/view'],
            latestUrl: ['/debug/default/view', 'tag' => 'oldest'],
            prevUrl: [],
            nextUrl: ['/debug/default/view', 'tag' => 'older'],
            onFirst: true,
            onLatest: false,
            hasPrev: false,
            hasNext: true,
        );
    }
}
