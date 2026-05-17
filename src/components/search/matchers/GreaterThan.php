<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Matches candidate values strictly greater than the configured base value.
 */
class GreaterThan extends Base
{
    /**
     * Returns whether the candidate is strictly greater than the base value.
     *
     * @param mixed $value Candidate value to test.
     *
     * @return bool `true` when `$value > $baseValue`, `false` otherwise.
     */
    public function match(mixed $value): bool
    {
        return $value > $this->baseValue;
    }
}
