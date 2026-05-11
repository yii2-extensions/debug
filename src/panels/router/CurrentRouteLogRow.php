<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use function is_string;

/**
 * Typed view-model for one row in the Current Route rules-tested log table.
 *
 * Encapsulates the `array<string, mixed>` entries inside {@see \yii\debug\models\router\CurrentRoute::$logs} so the
 * detail view stays free of {@see is_array()} / {@see is_string()} narrowing.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class CurrentRouteLogRow
{
    public function __construct(
        /**
         * Rule name or its class FQCN as captured by the route resolver.
         */
        public string $rule,
        /**
         * Parent rule ({@see \yii\rest\UrlRule} for nested REST rules); empty when there is no parent.
         */
        public string $parent,
        /**
         * `true` when this rule produced the final match; the renderer surfaces the row with a success modifier.
         */
        public bool $match,
    ) {}

    /**
     * Narrows the loose array shape into a typed row.
     *
     * @param array<string, mixed> $row
     */
    public static function from(array $row): self
    {
        return new self(
            rule: self::asString($row['rule'] ?? ''),
            parent: self::asString($row['parent'] ?? ''),
            match: ($row['match'] ?? false) === true,
        );
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
