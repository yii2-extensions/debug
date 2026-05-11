<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\panels\router\RouterRenderer;

/**
 * Unit tests for {@see RouterRenderer} covering the tab strip (three navigable tabs + the read-only badge chips for
 * Pretty URL / Strict Parsing / Global Suffix), the callout block in the Current Route panel and the empty-state
 * headings for the Router Rules and Action Routes tables.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('router')]
final class RouterRendererTest extends TestCase
{
    public function testRenderTabsActionRoutesPanelShowsEmptyStateWhenNoRoutesScanned(): void
    {
        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'No actions configured.',
            $html,
            'Empty actions list must show the dedicated heading.',
        );
    }

    public function testRenderTabsMarksCurrentRouteAsTheActivePanel(): void
    {
        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'id="r-tab-0"',
            $html,
            "First panel must carry the 'r-tab-0' id.",
        );
        self::assertStringContainsString(
            'yii-debug-tab-panel is-active',
            $html,
            "Active panel must carry the 'is-active' modifier.",
        );
        self::assertStringContainsString(
            'aria-selected="true"',
            $html,
            'First tab anchor must be aria-selected.',
        );
    }

    public function testRenderTabsOmitsGlobalSuffixBadgeWhenSuffixIsEmpty(): void
    {
        $rules = new RouterRules();

        $rules->suffix = '';

        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), $rules, new ActionRoutes());

        self::assertStringNotContainsString(
            'Global Suffix:',
            $html,
            'Empty suffix must not surface the badge.',
        );
    }

    public function testRenderTabsRendersCalloutWithResolvedRouteWhenMatchFailed(): void
    {
        $current = new CurrentRoute();

        $current->hasMatch = false;
        $current->message = 'No matching route.';
        $current->route = 'site/index';
        $current->action = 'app\\controllers\\SiteController::actionIndex()';

        $html = RouterRenderer::renderTabs($current, new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'yii-debug-router-callout',
            $html,
            'Message must surface as a callout block.',
        );
        self::assertStringContainsString(
            'Resolved route',
            $html,
            'Failed match must expose the resolved route row.',
        );
        self::assertStringContainsString(
            'Dispatched action',
            $html,
            'Failed match must expose the dispatched action row.',
        );
        self::assertStringContainsString(
            'site/index',
            $html,
            'Resolved route value must render inside the callout.',
        );
    }

    public function testRenderTabsRendersGlobalSuffixBadgeWithWarningVariant(): void
    {
        $rules = new RouterRules();

        $rules->suffix = '.html';

        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), $rules, new ActionRoutes());

        self::assertStringContainsString(
            'yii-debug-badge-warning',
            $html,
            'Suffix badge must carry the warning variant.',
        );
        self::assertStringContainsString(
            'Global Suffix: .html',
            $html,
            'Suffix value must surface inside the badge label.',
        );
    }

    public function testRenderTabsRendersPrettyUrlSuccessBadgeWhenEnabled(): void
    {
        $rules = new RouterRules();

        $rules->prettyUrl = true;

        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), $rules, new ActionRoutes());

        self::assertStringContainsString(
            'yii-debug-badge--success',
            $html,
            'Enabled Pretty URL must carry the success variant.',
        );
        self::assertStringContainsString(
            'Pretty URL Enabled',
            $html,
            "Pretty URL badge must show the 'Enabled' label.",
        );
    }

    public function testRenderTabsRendersRouterRulesEmptyStateWhenNoRulesConfigured(): void
    {
        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'No routing rules configured.',
            $html,
            'Empty rules list must show the dedicated heading.',
        );
    }

    public function testRenderTabsRendersStrictParsingMutedBadgeWhenDisabled(): void
    {
        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'yii-debug-badge--muted',
            $html,
            'Disabled Strict Parsing must carry the muted variant.',
        );
        self::assertStringContainsString(
            'Strict Parsing Disabled',
            $html,
            "Strict Parsing badge must show the 'Disabled' label.",
        );
    }

    public function testRenderTabsRendersThreeNavigableTabs(): void
    {
        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'href="#r-tab-0"',
            $html,
            'First tab must point to its panel.',
        );
        self::assertStringContainsString(
            'href="#r-tab-1"',
            $html,
            'Second tab must point to its panel.',
        );
        self::assertStringContainsString(
            'href="#r-tab-2"',
            $html,
            'Third tab must point to its panel.',
        );
    }

    public function testRenderTabsSuppressesCalloutBlockWhenMessageIsNull(): void
    {
        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes());

        self::assertStringNotContainsString(
            'yii-debug-router-callout',
            $html,
            "'null' message must not surface the callout block.",
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

    private function bareCurrentRoute(): CurrentRoute
    {
        return new CurrentRoute();
    }
}
