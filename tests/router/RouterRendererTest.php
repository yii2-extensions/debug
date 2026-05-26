<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\panels\router\RouterRenderer;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see RouterRenderer} covering the tab strip (three navigable tabs + the read-only badge chips for
 * Pretty URL / Strict Parsing / Global Suffix), the callout block in the Current Route panel and the empty-state
 * headings for the Router Rules and Action Routes tables.
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

    public function testRenderTabsCalloutOmitsResolvedDlWhenMatchSucceeded(): void
    {
        $current = new CurrentRoute();

        $current->hasMatch = true;
        $current->message = 'Matched site/index.';

        $html = RouterRenderer::renderTabs($current, new RouterRules(), new ActionRoutes());

        self::assertStringContainsString(
            'yii-debug-router-callout',
            $html,
            'Callout block must surface when a message is present.',
        );
        self::assertStringNotContainsString(
            'Resolved route',
            $html,
            "Successful matches must NOT render the 'Resolved route' row.",
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

    public function testRenderTabsOmitsCurrentRouteHeadingWhenNoRulesTested(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/<h3>\s*\.\s*<\/h3>/',
            RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes()),
            "No rules tested must not surface a lone '.' heading.",
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

    public function testRenderTabsRendersActionRoutesTableWithDiscoveredRows(): void
    {
        $actionRoutes = new ActionRoutes();

        $actionRoutes->routes = [
            'app\\controllers\\SiteController::actionIndex()' => [
                'route' => 'site/index',
                'rule' => 'home',
                'count' => 1,
            ],
            'app\\controllers\\SiteController::actionAbout()' => [
                'route' => 'site/about',
                'rule' => null,
                'count' => 3,
            ],
        ];

        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), $actionRoutes);

        self::assertMatchesRegularExpression(
            '/<th>\s*Action\s*<\/th>/',
            $html,
            'Action Routes table must carry the Action column header.',
        );
        self::assertStringContainsString(
            'SiteController::actionIndex()',
            $html,
            'First action FQCN must surface as a row.',
        );
        self::assertMatchesRegularExpression(
            '/<td>\s*home\s*<\/td>/',
            $html,
            'Matched-rule name must surface inside the row.',
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

    public function testRenderTabsRendersCurrentRouteHeadingWhenRulesTested(): void
    {
        $current = new CurrentRoute();

        $current->count = 3;
        $current->hasMatch = true;

        $html = RouterRenderer::renderTabs($current, new RouterRules(), new ActionRoutes());

        self::assertMatchesRegularExpression(
            '/<h3>\s*Tested 3 rules before match\.\s*<\/h3>/',
            $html,
            'Tested rules must surface the count and the match suffix.',
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

    public function testRenderTabsRendersLogsTableWithMatchingRuleHighlight(): void
    {
        $current = new CurrentRoute();

        $current->logs = [
            [
                'rule' => 'home',
                'match' => true,
                'parent' => '',
            ],
            [
                'rule' => 'about',
                'match' => false,
                'parent' => 'admin',
            ],
        ];

        $html = RouterRenderer::renderTabs($current, new RouterRules(), new ActionRoutes());

        self::assertMatchesRegularExpression(
            '/<th>\s*Rule\s*<\/th>/',
            $html,
            'Current-route logs table must carry the Rule column header.',
        );
        self::assertStringContainsString(
            'yii-debug-row-success',
            $html,
            "Matching rule rows must carry the 'yii-debug-row-success' modifier.",
        );
        self::assertMatchesRegularExpression(
            '/<td>\s*admin\s*<\/td>/',
            $html,
            'Parent column must surface the parent rule name when present.',
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

    public function testRenderTabsRendersRouterRulesTableWithFlattenedRules(): void
    {
        $rules = new RouterRules();

        $rules->rules = [
            [
                'name' => 'home',
                'route' => 'site/index',
                'verb' => ['GET'],
                'suffix' => null,
                'mode' => null,
                'type' => null,
            ],
            [
                'name' => 'api',
                'route' => 'api/<id>',
                'verb' => ['POST'],
                'suffix' => '.json',
                'mode' => 'parsing only',
                'type' => 'REST',
            ],
        ];

        $html = RouterRenderer::renderTabs($this->bareCurrentRoute(), $rules, new ActionRoutes());

        self::assertMatchesRegularExpression(
            '/<th>\s*Rule\s*<\/th>/',
            $html,
            'Router Rules table must carry the Rule column header.',
        );
        self::assertStringContainsString(
            'api/&lt;id&gt;',
            $html,
            'Second rule target must surface (HTML-escaped).',
        );
        self::assertMatchesRegularExpression(
            '/<td>\s*parsing only\s*<\/td>/',
            $html,
            "Mode column must surface 'parsing only' for the parsing-only rule.",
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
        self::assertStringNotContainsString(
            'yii-debug-router-callout',
            RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes()),
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
