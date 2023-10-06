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
    protected mixed $baseValue;

    /**
     * {@inheritdoc}
     */
    public function setValue(mixed $value): void
    {
        $this->baseValue = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function hasValue(): bool
    {
        return !empty($this->baseValue) || ($this->baseValue === '0');
    }
}
