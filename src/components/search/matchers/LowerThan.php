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
 * Checks if the given value is lower than the base one.
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 *
 * @since 2.0
 */
class LowerThan extends Base
{
    public function match(mixed $value): bool
    {
        return $value < $this->baseValue;
    }
}
