<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * Typed view-model for one row in the Action Routes table.
 *
 * Encapsulates the loosely-typed `array<string, mixed>` produced by {@see \yii\debug\models\router\ActionRoutes} into a
 * `final readonly` shape so the detail view stays free of {@see is_array()} / {@see is_string()} narrowing.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class ActionRouteRow
{
    public function __construct(
        /**
         * Action FQCN as the table key ('app\\controllers\\SiteController::actionIndex').
         */
        public string $action,
        /**
         * Route that resolves to the action ('site/index').
         */
        public string $route,
        /**
         * First rule that matched the route, if any; empty when no rule was tested or none matched.
         */
        public string $rule,
        /**
         * Number of rules tested before the match. '0' when no rule was tested.
         */
        public int $count,
    ) {}

    /**
     * Narrows the loose array shape ('$actionRoutes->routes[$action]') into a typed row.
     *
     * @param array<string, mixed> $row
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

    private static function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
