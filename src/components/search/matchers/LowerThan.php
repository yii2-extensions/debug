<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

/**
 * Checks if the given value is lower than the base one.
 */
class LowerThan extends Base
{
    /**
     * {@inheritdoc}
     */
    public function match(mixed $value): bool
    {
        return $value < $this->baseValue;
    }
}
