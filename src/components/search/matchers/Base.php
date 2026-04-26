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
     * Base value to check
     */
    protected mixed $baseValue = null;

    /**
     * Checks if the base value is set and not empty.
     *
     * @return bool `true` if the base value is set and not empty, `false` otherwise.
     */
    public function hasValue(): bool
    {
        return match (true) {
            $this->baseValue === null,
            $this->baseValue === '',
            $this->baseValue === false,
            $this->baseValue === 0,
            $this->baseValue === 0.0,
            $this->baseValue === [] => false,
            default => true,
        };
    }

    /**
     * Sets the base value to check.
     *
     * @param mixed $value Value to set as the base value.
     */
    public function setValue(mixed $value): void
    {
        $this->baseValue = $value;
    }
}
