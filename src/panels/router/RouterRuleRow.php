<?php

declare(strict_types=1);

namespace yii\debug\panels\router;

use function array_map;
use function implode;
use function is_array;
use function is_string;

/**
 * Typed view-model for one row in the Router Rules table.
 *
 * Narrows the loosely-typed `array<string, mixed>` produced by {@see \yii\debug\models\router\RouterRules} into typed
 * properties, so the detail view stays free of {@see is_array()} narrowing on each cell access.
 */
final readonly class RouterRuleRow
{
    public function __construct(
        /**
         * Rule name or its class FQCN.
         */
        public string $name,
        /**
         * Route target the rule maps to (for example, `site/index` or `<controller>/<action>`).
         */
        public string $route,
        /**
         * Comma-separated verb list (for example, `GET, POST`), or `''` when the rule does not restrict verbs.
         */
        public string $verb,
        /**
         * URL suffix declared on the rule (for example, `.html`), or `''` when the rule inherits the global suffix.
         */
        public string $suffix,
        /**
         * Routing mode (`PARSING ONLY` / `CREATION ONLY` / `BOTH`), or `''` when the rule did not surface a mode.
         */
        public string $mode,
        /**
         * Rule type, typically the FQCN of the matching rule class.
         */
        public string $type,
    ) {}

    /**
     * Narrows the loose array shape captured by {@see \yii\debug\models\router\RouterRules::$rules} into a typed row.
     *
     * @param array<string, mixed> $row Source row.
     */
    public static function from(array $row): self
    {
        return new self(
            name: self::asString($row['name'] ?? ''),
            route: self::asString($row['route'] ?? ''),
            verb: self::verbAsString($row['verb'] ?? null),
            suffix: self::asString($row['suffix'] ?? ''),
            mode: self::asString($row['mode'] ?? ''),
            type: self::asString($row['type'] ?? ''),
        );
    }

    /**
     * Returns the value when it is already a string, falling back to `''` otherwise.
     */
    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Joins a verb list into a comma-separated string, or returns the value as-is when already a string.
     *
     * Non-string array entries collapse to `''` to keep the joined output safe to render.
     */
    private static function verbAsString(mixed $value): string
    {
        if (is_array($value)) {
            return implode(
                ', ',
                array_map(
                    static fn(mixed $element): string => is_string($element) ? $element : '',
                    $value,
                ),
            );
        }

        return self::asString($value);
    }
}
