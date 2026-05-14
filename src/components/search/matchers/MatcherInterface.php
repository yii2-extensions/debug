<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Contract for {@see \yii\debug\components\search\Filter} matcher rules.
 *
 * Implementations hold a base value (set via {@see setValue()}) and decide through {@see match()} whether candidate
 * values pass. {@see \yii\debug\components\search\Filter::addMatcher()} ignores rules whose {@see hasValue()} returns
 * `false`.
 */
interface MatcherInterface
{
    /**
     * Returns whether the base value is set and not considered empty.
     *
     * @return bool `true` when the base value is meaningful, `false` otherwise.
     */
    public function hasValue(): bool;

    /**
     * Returns whether the candidate value matches the configured base value.
     *
     * @param mixed $value Candidate value to test.
     *
     * @return bool `true` when the candidate satisfies the rule, `false` otherwise.
     */
    public function match(mixed $value): bool;

    /**
     * Sets the base value to match against.
     *
     * @param mixed $value Reference value for subsequent {@see match()} calls.
     */
    public function setValue(mixed $value): void;
}
