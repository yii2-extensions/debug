<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Matches candidate values greater than or equal to the configured base value.
 */
class GreaterThanOrEqual extends Base
{
    /**
     * Returns whether the candidate is greater than or equal to the base value.
     *
     * @param mixed $value Candidate value to test.
     *
     * @return bool `true` when `$value >= $baseValue`, `false` otherwise.
     */
    public function match(mixed $value): bool
    {
        return $value >= $this->baseValue;
    }
}
