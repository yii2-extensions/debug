<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\components\search\matchers;

use yii\base\Component;

/**
 * Base class for matchers that are used in a filter.
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 *
 * @since 2.0
 */
abstract class Base extends Component implements MatcherInterface
{
    /**
     * @var mixed base value to check.
     */
    protected mixed $baseValue = null;

    public function setValue(mixed $value): void
    {
        $this->baseValue = $value;
    }

    public function hasValue(): bool
    {
        return !empty($this->baseValue) || ($this->baseValue === '0');
    }
}
