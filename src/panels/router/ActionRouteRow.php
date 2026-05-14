<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * Typed view-model for one row in the Action Routes table.
 *
 * Narrows the loosely-typed `array<string, mixed>` produced by {@see \yii\debug\models\router\ActionRoutes} into typed
 * properties, so the detail view stays free of {@see is_array()} / {@see is_string()} narrowing.
 */
final readonly class ActionRouteRow
{
    public function __construct(
        /**
         * Action FQCN used as the table key (for example, `app\controllers\SiteController::actionIndex`).
         */
        public string $action,
        /**
         * Route that resolves to the action (for example, `site/index`).
         */
        public string $route,
        /**
         * First rule that matched the route, or `''` when no rule was tested or none matched.
         */
        public string $rule,
        /**
         * Number of rules tested before the match, or `0` when no rule was tested.
         */
        public int $count,
    ) {}

    /**
     * Narrows the loose array shape (`$actionRoutes->routes[$action]`) into a typed row.
     *
     * @param string $action Action FQCN used as the row key.
     * @param array<string, mixed> $row Source row.
     */
    public static function from(string $action, array $row): self
    {
        return new self(
            action: $action,
            route: self::asString($row['route'] ?? ''),
            rule: self::asString($row['rule'] ?? ''),
            count: self::asInt($row['count'] ?? 0),
        );
    }

    /**
     * Coerces the value to an int, falling back to `0` when it is neither an int nor a numeric string.
     */
    private static function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Returns the value when it is already a string, falling back to `''` otherwise.
     */
    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
