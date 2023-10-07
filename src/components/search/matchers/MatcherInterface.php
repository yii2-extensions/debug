<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * MatcherInterface should be implemented by all matchers that are used in a filter.
 */
interface MatcherInterface
{
    /**
     * Checks if the value passed matches base value.
     *
     * @param mixed $value value to be matched.
     *
     * @return bool if there is a match.
     */
    public function match(mixed $value): bool;

    /**
     * Sets base value to match against.
     */
    public function setValue(mixed $value);

    /**
     * Checks if base value is set.
     *
     * @return bool if base value is set.
     */
    public function hasValue(): bool;
}
