<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use PHPForge\Debug\Panel\Router\{
    ActionRouteRow,
    RouterCurrentView,
    RouterRuleRow,
    RouterSectionRenderer,
};
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};

/**
 * Adapts Yii2 router models to the shared framework-neutral Router panel renderer.
 */
final class RouterRenderer
{
    /**
     * Renders the shared Router detail from Yii2 current-route, rule, and action models.
     */
    public static function renderTabs(
        CurrentRoute $currentRoute,
        RouterRules $routerRules,
        ActionRoutes $actionRoutes,
    ): string {
        $current = new RouterCurrentView(
            action: $currentRoute->action,
            count: $currentRoute->count,
            hasMatch: $currentRoute->hasMatch,
            logs: $currentRoute->logs,
            message: $currentRoute->message,
            route: $currentRoute->route,
        );
        $ruleRows = [];

        foreach ($routerRules->rules as $rule) {
            $ruleRows[] = RouterRuleRow::from($rule);
        }

        $actionRows = [];

        foreach ($actionRoutes->routes as $action => $route) {
            $actionRows[] = ActionRouteRow::from($action, $route);
        }

        $badges = [
            [
                'label' => 'Pretty URL ' . ($routerRules->prettyUrl ? 'Enabled' : 'Disabled'),
                'variant' => $routerRules->prettyUrl ? 'success' : 'muted',
            ],
            [
                'label' => 'Strict Parsing ' . ($routerRules->strictParsing ? 'Enabled' : 'Disabled'),
                'variant' => $routerRules->strictParsing ? 'success' : 'muted',
            ],
        ];

        if ($routerRules->suffix !== null && $routerRules->suffix !== '') {
            $badges[] = ['label' => "Global Suffix: {$routerRules->suffix}", 'variant' => 'warning'];
        }

        return RouterSectionRenderer::renderTabs($current, $ruleRows, $actionRows, $badges);
    }
}
