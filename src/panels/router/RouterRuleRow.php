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
 * Encapsulates the loosely-typed `array<string, mixed>` produced by {@see \yii\debug\models\router\RouterRules} into a
 * `final readonly` shape so the detail view stays free of {@see is_array()} narrowing on each cell access.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class RouterRuleRow
{
    public function __construct(
        /**
         * Rule name or its class FQCN.
         */
        public string $name,
        /**
         * Route target the rule maps to ('site/index', '<controller>/<action>', ...).
         */
        public string $route,
        /**
         * Verb list ('GET, POST') joined into a comma-separated string; empty when the rule does not restrict verbs.
         */
        public string $verb,
        /**
         * URL suffix declared on the rule ('.html'); empty when the rule inherits the global suffix.
         */
        public string $suffix,
        /**
         * Routing mode ('PARSING ONLY' / 'CREATION ONLY' / 'BOTH'); empty when the rule did not surface a mode.
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
     * @param array<string, mixed> $row
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

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

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
