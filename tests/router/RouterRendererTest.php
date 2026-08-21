<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPForge\Debug\Panel\Router\CurrentRouteLogRow;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\panels\router\RouterRenderer;
use yii\debug\tests\support\TestCase;

use function substr_count;

/**
 * Unit tests for {@see RouterRenderer} covering the tab strip (three navigable tabs + the read-only badge chips for
 * Pretty URL / Strict Parsing / Global Suffix), the route summary and callout block in the Current Route panel, and
 * the empty-state headings for the three section panes.
 */
#[Group('panel')]
#[Group('router')]
final class RouterRendererTest extends TestCase
{
    public function testRenderTabsActionRoutesPanelShowsEmptyStateWhenNoRoutesScanned(): void
    {
        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'No actions configured.',
            $html,
            'Empty actions list must show the dedicated heading.',
        );
    }

    public function testRenderTabsMarksCurrentRouteAsTheActivePanel(): void
    {
        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'id="router-panel-0"',
            $html,
            "First panel must carry the 'router-panel-0' id.",
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
            '/<h2>\s*\.\s*<\/h2>/',
            RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes()),
            "No rules tested must not surface a lone '.' heading.",
        );
    }

    public function testRenderTabsOmitsGlobalSuffixBadgeWhenSuffixIsEmpty(): void
    {
        $rules = new RouterRules();

        $rules->suffix = '';

        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            $rules,
            new ActionRoutes(),
        );

        self::assertStringNotContainsString(
            'Global Suffix:',
            $html,
            'Empty suffix must not surface the badge.',
        );
    }

    public function testRenderTabsOmitsRouteSummaryWhenRouteAndActionAreEmpty(): void
    {
        $current = new CurrentRoute();

        $current->hasMatch = true;
        $current->message = 'Matched site/index.';

        $html = RouterRenderer::renderTabs(
            $current,
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'yii-debug-router-callout',
            $html,
            'Callout block must surface when a message is present.',
        );
        self::assertStringNotContainsString(
            'yii-debug-router-summary',
            $html,
            'Empty route and action must not surface the summary.',
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

        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            new RouterRules(),
            $actionRoutes,
        );

        self::assertMatchesRegularExpression(
            '/<th scope="col">\s*Action\s*<\/th>/',
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

    public function testRenderTabsRendersCurrentRouteEmptyStateWhenNothingCaptured(): void
    {
        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'No route resolution captured.',
            $html,
            'Bare capture must show the dedicated heading.',
        );
    }

    public function testRenderTabsRendersCurrentRouteHeadingWhenRulesTested(): void
    {
        $current = new CurrentRoute();

        $current->count = 3;
        $current->hasMatch = true;

        self::assertMatchesRegularExpression(
            '/<h2>\s*Tested 3 rules before match\.\s*<\/h2>/',
            RouterRenderer::renderTabs($current, new RouterRules(), new ActionRoutes()),
            'Tested rules must surface the count and the match suffix.',
        );
    }

    public function testRenderTabsRendersGlobalSuffixBadgeWithWarningVariant(): void
    {
        $rules = new RouterRules();

        $rules->suffix = '.html';

        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            $rules,
            new ActionRoutes(),
        );

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
            new CurrentRouteLogRow('home', '', true),
            new CurrentRouteLogRow('about', 'admin', false),
        ];

        $html = RouterRenderer::renderTabs(
            $current,
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertMatchesRegularExpression(
            '/<th scope="col">\s*Rule\s*<\/th>/',
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

        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            $rules,
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'yii-debug-badge-success',
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
        self::assertStringContainsString(
            'No routing rules configured.',
            RouterRenderer::renderTabs($this->bareCurrentRoute(), new RouterRules(), new ActionRoutes()),
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

        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            $rules,
            new ActionRoutes(),
        );

        self::assertMatchesRegularExpression(
            '/<th scope="col">\s*Rule\s*<\/th>/',
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

    public function testRenderTabsRendersRouteSummaryAlongsideCalloutWhenMatchFailed(): void
    {
        $current = new CurrentRoute();

        $current->hasMatch = false;
        $current->message = 'No matching route.';
        $current->route = 'site/index';
        $current->action = 'app\\controllers\\SiteController::actionIndex()';

        $html = RouterRenderer::renderTabs(
            $current,
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'yii-debug-router-callout',
            $html,
            'Message must surface as a callout block.',
        );
        self::assertStringContainsString(
            'Resolved route',
            $html,
            'Summary must expose the resolved route row.',
        );
        self::assertStringContainsString(
            'Dispatched action',
            $html,
            'Summary must expose the dispatched action row.',
        );
        self::assertStringContainsString(
            'site/index',
            $html,
            'Resolved route value must render inside the summary.',
        );
    }

    public function testRenderTabsRendersRouteSummaryWhenNoTraceMessagesCaptured(): void
    {
        $current = new CurrentRoute();

        $current->action = 'app\\controllers\\SiteController::actionIndex()';
        $current->route = 'site/index';

        $html = RouterRenderer::renderTabs(
            $current,
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'yii-debug-router-summary',
            $html,
            'Summary must render without trace messages.',
        );
        self::assertStringContainsString(
            'site/index',
            $html,
            'Resolved route value must surface in the pane.',
        );
        self::assertStringNotContainsString(
            'yii-debug-router-callout',
            $html,
            "'null' message must not surface the callout block.",
        );
    }

    public function testRenderTabsRendersStrictParsingMutedBadgeWhenDisabled(): void
    {
        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'yii-debug-badge-muted',
            $html,
            'Disabled Strict Parsing must carry the muted variant.',
        );
        self::assertStringContainsString(
            'Strict Parsing Disabled',
            $html,
            "Strict Parsing badge must show the 'Disabled' label.",
        );
        self::assertSame(
            2,
            substr_count($html, 'yii-debug-badge-muted'),
            'Only Strict Parsing and Global Suffix badges must carry the muted variant.',
        );
    }

    public function testRenderTabsRendersThreeNavigableTabs(): void
    {
        $html = RouterRenderer::renderTabs(
            $this->bareCurrentRoute(),
            new RouterRules(),
            new ActionRoutes(),
        );

        self::assertStringContainsString(
            'href="#router-panel-0"',
            $html,
            'First tab must point to its panel.',
        );
        self::assertStringContainsString(
            'href="#router-panel-1"',
            $html,
            'Second tab must point to its panel.',
        );
        self::assertStringContainsString(
            'href="#router-panel-2"',
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
