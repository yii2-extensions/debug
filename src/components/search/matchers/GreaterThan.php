<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Checks if the given value is greater than the base one.
 */
class GreaterThan extends Base
{
    public function match(mixed $value): bool
    {
        return $value > $this->baseValue;
    }
}
