<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\components\search\matchers;

/**
 * Checks if the given value is greater than or equal the base one.
 *
 * @author Dmitriy Bashkarev <dmitriy@bashkarev.com>
 *
 * @since 2.0.7
 */
class GreaterThanOrEqual extends Base
{
    public function match(mixed $value): bool
    {
        return $value >= $this->baseValue;
    }
}
