<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Checks if the given value is greater than the base one.
 */
class GreaterThan extends Base
{
    public function match($value)
    {
        return $value > $this->baseValue;
    }
}
