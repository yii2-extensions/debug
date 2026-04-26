<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * MatcherInterface should be implemented by all matchers that are used in a filter.
 */
interface MatcherInterface
{
    /**
     * Checks if base value is set.
     *
     * @return bool `true` if base value is set, `false` otherwise.
     */
    public function hasValue(): bool;

    /**
     * Checks if the value passed matches base value.
     *
     * @param mixed $value Value to be matched.
     *
     * @return bool `true` if the value passed matches base value, `false` otherwise.
     */
    public function match(mixed $value): bool;

    /**
     * Sets base value to match against.
     *
     * @param mixed $value Base value to match against.
     */
    public function setValue(mixed $value): void;
}
