<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

use yii\base\Component;

/**
 * Abstract base for {@see \yii\debug\components\search\Filter} matchers providing the shared base-value contract.
 *
 * Concrete subclasses only need to implement {@see MatcherInterface::match()}; the base value plumbing and the
 * empty-base-value semantics consumed by {@see \yii\debug\components\search\Filter::addMatcher()} live here.
 */
abstract class Base extends Component implements MatcherInterface
{
    /**
     * Reference value subsequent {@see MatcherInterface::match()} calls compare against.
     */
    protected mixed $baseValue = null;

    /**
     * Returns whether the base value is set and not considered empty.
     *
     * Treats `null`, `''`, `false`, `0`, `0.0`, and `[]` as empty.
     *
     * @return bool `true` when the base value is meaningful, `false` otherwise.
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
     * Sets the base value to match against.
     *
     * @param mixed $value Reference value for subsequent {@see MatcherInterface::match()} calls.
     */
    public function setValue(mixed $value): void
    {
        $this->baseValue = $value;
    }
}
