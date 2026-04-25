<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

use yii\base\Component;

/**
 * Base class for matchers that are used in a filter.
 */
abstract class Base extends Component implements MatcherInterface
{
    /**
     * @var mixed base value to check
     */
    protected $baseValue;

    public function hasValue()
    {
        return !empty($this->baseValue) || ($this->baseValue === '0');
    }

    public function setValue($value)
    {
        $this->baseValue = $value;
    }
}
