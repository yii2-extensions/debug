<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Checks if the given value is lower than the base one.
 */
class LowerThan extends Base
{
    /**
     * Checks if the given value is lower than the base one.
     *
     * @param mixed $value Value to check.
     *
     * @return bool `true` if the given value is lower than the base one, `false` otherwise.
     */
    public function match(mixed $value): bool
    {
        return $value < $this->baseValue;
    }
}
