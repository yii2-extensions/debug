<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

use PHPForge\Debug\Panel\Request\Routing\{
    CurrentRouteView,
    RequestRoutingView,
    RouteBadge,
    RouteDefinition,
    RouteInventoryView,
    RouteTraceRow,
};
use PHPForge\Debug\Panel\Router\RouterSnapshot;
use PHPForge\Debug\Storage\ExceptionSnapshot;
use Throwable;
use Yii;
use yii\debug\models\router\RouterRules;
use yii\debug\Module;

use function array_diff;
use function array_is_list;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function is_array;
use function is_string;
use function preg_split;
use function sprintf;
use function str_ends_with;
use function strtoupper;
use function trim;

/**
 * Adapts Yii2 request data, the captured URL-rule trace, and the live URL manager configuration to the shared
 * Request routing view.
 */
final class RequestRoutingViewFactory
{
    private const string INVENTORY_SOURCE = 'Current URL manager configuration';

    /**
     * Builds the composed routing view without evaluating controller action maps.
     *
     * @param array<array-key, mixed> $data Captured Request panel data.
     * @param RouterSnapshot|null $snapshot Captured Router panel trace, when available.
     * @param ExceptionSnapshot|null $error Captured Router collector or hydration failure, when available.
     * @param RouterRules|null $routerRules Injected live URL-manager view model; primarily useful to avoid rescanning
     * the manager when the caller already has it.
     */
    public static function fromRequestData(
        array $data,
        RouterSnapshot|null $snapshot,
        ExceptionSnapshot|null $error,
        RouterRules|null $routerRules = null,
    ): RequestRoutingView {
        [$inventory, $excludedPatterns] = self::inventory($routerRules);

        $route = self::nonEmptyString($data['route'] ?? null);

        if ($route === null) {
            $route = $snapshot === null ? '' : $snapshot->route;
        }

        $action = self::nonEmptyString($data['action'] ?? null) ?? $snapshot?->action;

        $parameters = is_array($data['actionParams'] ?? null) ? $data['actionParams'] : [];

        $trace = self::trace($snapshot, $excludedPatterns);

        $onlyInternalRules = $trace === [] && ($snapshot?->entries() ?? []) !== [];

        return new RequestRoutingView(
            current: CurrentRouteView::create(route: $route)
                ->withAction($action)
                ->withParameters($parameters)
                ->withDefinition(self::currentDefinition($route, $trace, $inventory->getRoutes()))
                ->withMessage($onlyInternalRules ? null : $snapshot?->message)
                ->withTrace($trace)
                ->withError($error?->getMessage()),
            inventory: $inventory,
        );
    }

    /**
     * @param list<RouteTraceRow> $trace
     * @param list<RouteDefinition> $routes
     */
    private static function currentDefinition(string $route, array $trace, array $routes): RouteDefinition|null
    {
        foreach ($trace as $entry) {
            if (!$entry->matched) {
                continue;
            }

            foreach ($routes as $definition) {
                if (self::traceMatchesPattern($entry->rule, $definition->getPattern())) {
                    return $definition;
                }
            }
        }

        foreach ($routes as $definition) {
            if ($definition->getTarget() === $route) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array{RouteInventoryView, list<string>} Application inventory and omitted debugger rule patterns.
     */
    private static function inventory(RouterRules|null $routerRules): array
    {
        try {
            $routerRules ??= new RouterRules();
        } catch (Throwable $throwable) {
            return [
                RouteInventoryView::create(routes: [])
                    ->withSource(self::INVENTORY_SOURCE)
                    ->withError(sprintf(
                        'URL manager configuration could not be read: %s: %s',
                        $throwable::class,
                        $throwable->getMessage(),
                    )),
                [],
            ];
        }

        $routes = [];
        $excludedPatterns = [];

        foreach ($routerRules->rules as $rule) {
            $target = self::nonEmptyString($rule['route'] ?? null);

            if (self::isDebuggerRoute($target ?? '')) {
                $excludedPatterns[] = self::nonEmptyString($rule['name'] ?? null) ?? '';

                continue;
            }

            $routes[] = RouteDefinition::create(pattern: self::nonEmptyString($rule['name'] ?? null) ?? '')
                ->withMethods(self::methods($rule['verb'] ?? null))
                ->withTarget($target)
                ->withSuffix(self::nonEmptyString($rule['suffix'] ?? null))
                ->withMode(self::nonEmptyString($rule['mode'] ?? null))
                ->withType(self::nonEmptyString($rule['type'] ?? null));
        }

        $badges = [
            new RouteBadge(
                'Pretty URL ' . ($routerRules->prettyUrl ? 'Enabled' : 'Disabled'),
                $routerRules->prettyUrl ? 'success' : 'muted',
            ),
            new RouteBadge(
                'Strict Parsing ' . ($routerRules->strictParsing ? 'Enabled' : 'Disabled'),
                $routerRules->strictParsing ? 'success' : 'muted',
            ),
        ];

        if ($routerRules->suffix !== null && $routerRules->suffix !== '') {
            $badges[] = new RouteBadge("Global Suffix: {$routerRules->suffix}", 'warning');
        }

        // A shared pattern cannot identify debugger ownership in historical trace labels.
        $applicationPatterns = array_map(static fn(RouteDefinition $route): string => $route->getPattern(), $routes);

        return [
            RouteInventoryView::create(routes: $routes)
                ->withBadges($badges)
                ->withSource(self::INVENTORY_SOURCE),
            array_values(array_diff($excludedPatterns, $applicationPatterns)),
        ];
    }

    private static function isDebuggerRoute(string $route): bool
    {
        $module = Yii::$app;

        foreach (explode('/', trim($route, '/')) as $id) {
            $module = $module->getModule($id, false);

            if ($module instanceof Module) {
                return true;
            }

            if ($module === null) {
                return false;
            }
        }

        return false;
    }

    /**
     * Normalizes every Yii2 URL-rule verb representation to a stable list of non-empty strings.
     *
     * @return list<string>
     */
    private static function methods(mixed $value): array
    {
        if (is_string($value)) {
            $parts = preg_split('/[\s,|]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

            if ($parts === false) {
                return [];
            }

            return array_values(array_unique(array_map(strtoupper(...), $parts)));
        }

        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $methods = [];

        foreach ($value as $method) {
            if (is_string($method) && trim($method) !== '') {
                $methods[] = strtoupper(trim($method));
            }
        }

        return array_values(array_unique($methods));
    }

    private static function nonEmptyString(mixed $value): string|null
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param list<string> $excludedPatterns
     *
     * @return list<RouteTraceRow>
     */
    private static function trace(RouterSnapshot|null $snapshot, array $excludedPatterns): array
    {
        $trace = [];

        foreach ($snapshot?->entries() ?? [] as $entry) {
            foreach ($excludedPatterns as $pattern) {
                if (self::traceMatchesPattern($entry->rule, $pattern)) {
                    continue 2;
                }
            }

            $trace[] = new RouteTraceRow(
                rule: $entry->rule,
                parent: $entry->parent,
                matched: $entry->match,
            );
        }

        return $trace;
    }

    private static function traceMatchesPattern(string $traceRule, string $pattern): bool
    {
        return $pattern !== '' && ($traceRule === $pattern || str_ends_with($traceRule, " {$pattern}"));
    }
}
