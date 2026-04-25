<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Checks if the given value is lower than the base one.
 */
class LowerThan extends Base
{
    public function match($value)
    {
        return $value < $this->baseValue;
    }
}
