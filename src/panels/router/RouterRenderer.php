<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\H3;
use UIAwesome\Html\List\{Dd, Dl, Dt, Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};

use function count;

/**
 * Renders the Router panel detail view.
 *
 * Stateless static helpers: each public method takes a typed model or row and returns a fully-rendered HTML string.
 * Concentrates tab-strip wiring, badge tinting, the three section tables (Current Route logs / Router Rules / Action
 * Routes), and the callout block in one testable place.
 */
final class RouterRenderer
{
    /**
     * Renders the entire Router panel detail: the router-wide flags strip (Pretty URL / Strict Parsing / Global
     * Suffix badges), the tab strip (Current Route / Router Rules / Action Routes), and the per-tab content panels.
     *
     * @param CurrentRoute $currentRoute Current-route resolver snapshot.
     * @param RouterRules $routerRules Flattened URL-rules snapshot.
     * @param ActionRoutes $actionRoutes Discovered controller actions and matching rules.
     */
    public static function renderTabs(
        CurrentRoute $currentRoute,
        RouterRules $routerRules,
        ActionRoutes $actionRoutes,
    ): string {
        return self::renderFlagsStrip($routerRules)
            . self::renderTabNav()
            . self::renderTabPanels($currentRoute, $routerRules, $actionRoutes);
    }

    /**
     * Renders the Action Routes section as a `<table>` of action FQCN → route, first matching rule, and rules tested.
     */
    private static function renderActionRoutesPanel(ActionRoutes $actionRoutes): string
    {
        if ($actionRoutes->routes === []) {
            return H3::tag()->content('No actions configured.')->render();
        }

        $rows = [];
        $i = 1;

        foreach ($actionRoutes->routes as $action => $route) {
            $row = ActionRouteRow::from($action, $route);

            $rows[] = Tr::tag()
                ->html(
                    Td::tag()->content((string) $i),
                    Td::tag()->content($row->action),
                    Td::tag()->content($row->route),
                    Td::tag()->content($row->rule),
                    Td::tag()->content((string) $row->count),
                );

            $i++;
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()->html(
                                    Th::tag()->content('#'),
                                    Th::tag()->content('Action'),
                                    Th::tag()->content('Route'),
                                    Th::tag()->content('First Matching Rule'),
                                    Th::tag()->content('Rules Tested'),
                                ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders one read-only badge chip for the flags strip.
     *
     * Non-clickable; surfaces a router-wide flag (Pretty URL / Strict Parsing / Global Suffix).
     */
    private static function renderBadgeChip(string $label, string $variant): Span
    {
        return Span::tag()
            ->class("yii-debug-badge yii-debug-badge-{$variant}")
            ->content($label);
    }

    /**
     * Renders the callout block surfaced by {@see CurrentRoute::$message}.
     *
     * Shows an info banner with the resolver explanation captured for the routing pass.
     */
    private static function renderCalloutBlock(CurrentRoute $currentRoute): string
    {
        if ($currentRoute->message === null) {
            return '';
        }

        return Div::tag()
            ->class('yii-debug-callout yii-debug-callout-info yii-debug-router-callout')
            ->html(
                P::tag()
                    ->class('yii-debug-router-callout-message')
                    ->content($currentRoute->message),
            )
            ->render();
    }

    /**
     * Renders the Current Route section: route summary, heading, callout block, and rules-tested log table.
     *
     * Falls back to a dedicated empty-state heading when no route resolution data was captured for the request.
     */
    private static function renderCurrentRoutePanel(CurrentRoute $currentRoute): string
    {
        $heading = $currentRoute->count === 0
            ? ''
            : H3::tag()
                ->content(
                    Yii::$app->i18n->format(
                        '{rulesTested, plural, =1{Tested # rule} other{Tested # rules}}'
                            . '{hasMatch, plural, =0{} other{ before match}}.',
                        [
                            'rulesTested' => $currentRoute->count,
                            'hasMatch' => (int) $currentRoute->hasMatch,
                        ],
                        'en_US',
                    ),
                )
                ->render();

        $body = self::renderRouteSummary($currentRoute)
            . $heading
            . self::renderCalloutBlock($currentRoute)
            . self::renderLogsTable($currentRoute);

        return $body === ''
            ? H3::tag()->content('No route resolution captured.')->render()
            : $body;
    }

    /**
     * Renders the router-wide flags strip: Pretty URL / Strict Parsing badges plus the optional Global Suffix badge.
     *
     * Reuses the shared `yii-debug-grid-summary` strip so the read-only flags sit above the tab row instead of
     * competing with the navigable tabs.
     */
    private static function renderFlagsStrip(RouterRules $routerRules): string
    {
        $badges = [
            self::renderBadgeChip(
                'Pretty URL ' . ($routerRules->prettyUrl ? 'Enabled' : 'Disabled'),
                $routerRules->prettyUrl ? 'success' : 'muted',
            ),
            self::renderBadgeChip(
                'Strict Parsing ' . ($routerRules->strictParsing ? 'Enabled' : 'Disabled'),
                $routerRules->strictParsing ? 'success' : 'muted',
            ),
        ];

        if ($routerRules->suffix !== null && $routerRules->suffix !== '') {
            $badges[] = self::renderBadgeChip("Global Suffix: {$routerRules->suffix}", 'warning');
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$badges)
            ->render();
    }

    /**
     * Renders the rules-tested log table beneath the Current Route callout.
     *
     * Returns `''` when there are no captured logs, since the heading already conveys the `Tested 0 rules` state.
     */
    private static function renderLogsTable(CurrentRoute $currentRoute): string
    {
        if (count($currentRoute->logs) === 0) {
            return '';
        }

        $rows = [];

        foreach ($currentRoute->logs as $i => $log) {
            $row = CurrentRouteLogRow::from($log);

            $tr = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($i + 1)),
                    Td::tag()->content($row->rule),
                    Td::tag()->content($row->parent),
                );

            if ($row->match) {
                $tr = $tr->class('yii-debug-row-success');
            }

            $rows[] = $tr;
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()->html(
                                    Th::tag()->content('#'),
                                    Th::tag()->content('Rule'),
                                    Th::tag()->content('Parent'),
                                ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders the Router Rules section as a `<table>` of rule → target, verb, suffix, mode, and type.
     */
    private static function renderRouterRulesPanel(RouterRules $routerRules): string
    {
        if (count($routerRules->rules) === 0) {
            return H3::tag()
                ->content('No routing rules configured.')
                ->render();
        }

        $rows = [];

        foreach ($routerRules->rules as $i => $rule) {
            $row = RouterRuleRow::from($rule);

            $rows[] = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($i + 1)),
                    Td::tag()->content($row->name),
                    Td::tag()->content($row->route),
                    Td::tag()->content($row->verb),
                    Td::tag()->content($row->suffix),
                    Td::tag()->content($row->mode),
                    Td::tag()->content($row->type),
                );
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()->html(
                                    Th::tag()->content('#'),
                                    Th::tag()->content('Rule'),
                                    Th::tag()->content('Target'),
                                    Th::tag()->content('Verb'),
                                    Th::tag()->content('Suffix'),
                                    Th::tag()->content('Mode'),
                                    Th::tag()->content('Type'),
                                ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders the resolved-route / dispatched-action summary `<dl>` for the Current Route section.
     *
     * Surfaces the captured route and action unconditionally, so the pane stays informative when the resolver left no
     * trace messages to replay.
     */
    private static function renderRouteSummary(CurrentRoute $currentRoute): string
    {
        if ($currentRoute->route === '' && $currentRoute->action === '') {
            return '';
        }

        $items = [];

        if ($currentRoute->route !== '') {
            $items[] = Dt::tag()
                ->content('Resolved route');
            $items[] = Dd::tag()
                ->html(Code::tag()->content($currentRoute->route));
        }

        if ($currentRoute->action !== '') {
            $items[] = Dt::tag()
                ->content('Dispatched action');
            $items[] = Dd::tag()
                ->html(Code::tag()->content($currentRoute->action));
        }

        return Dl::tag()
            ->class('yii-debug-router-summary')
            ->html(...$items)
            ->render();
    }

    /**
     * Renders the `<ul class="yii-debug-tabs">` strip with the three navigable tabs.
     */
    private static function renderTabNav(): string
    {
        $labels = ['Current Route', 'Router Rules', 'Action Routes'];

        $items = [];

        foreach ($labels as $k => $label) {
            $isActive = $k === 0;

            $items[] = Li::tag()
                ->class('yii-debug-tab')
                ->html(
                    A::tag()
                        ->addAttribute('aria-controls', 'r-tab-' . $k)
                        ->addAttribute('aria-selected', $isActive ? 'true' : 'false')
                        ->addAttribute('data-yii-debug-toggle', 'tab')
                        ->addAttribute('role', 'tab')
                        ->class($isActive ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link')
                        ->content($label)
                        ->href('#r-tab-' . $k),
                );
        }

        return Ul::tag()
            ->class('yii-debug-tabs')
            ->html(...$items)->render();
    }

    /**
     * Renders the per-tab content panels.
     *
     * `Current Route` is rendered active; the rest stay hidden until the toggle JS activates them.
     */
    private static function renderTabPanels(
        CurrentRoute $currentRoute,
        RouterRules $routerRules,
        ActionRoutes $actionRoutes,
    ): string {
        $panels = [
            self::renderCurrentRoutePanel($currentRoute),
            self::renderRouterRulesPanel($routerRules),
            self::renderActionRoutesPanel($actionRoutes),
        ];

        $children = [];

        foreach ($panels as $k => $body) {
            $children[] = Div::tag()
                ->class($k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel')
                ->id('r-tab-' . $k)
                ->html($body);
        }

        return Div::tag()
            ->class('yii-debug-tab-content')
            ->html(...$children)
            ->render();
    }
}
