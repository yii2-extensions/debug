<?php

declare(strict_types=1);

namespace yii\debug\components\search;

use yii\base\Component;
use yii\debug\components\search\matchers\MatcherInterface;

/**
 * Provides array filtering capabilities.
 */
class Filter extends Component
{
    /**
     * @var array<string, array<int, MatcherInterface>> Rules for matching filters in the way:
     * `[:fieldName => [rule1, rule2,..]]`
     */
    protected array $rules = [];

    /**
     * Adds data filtering rule.
     *
     * @param string $name Attribute name.
     * @param MatcherInterface $rule Rule to be applied for filtering.
     */
    public function addMatcher(string $name, MatcherInterface $rule): void
    {
        if ($rule->hasValue()) {
            $this->rules[$name][] = $rule;
        }
    }

    /**
     * Applies filter on a given array and returns filtered data.
     *
     * @param array<int, array<string, mixed>> $data data to filter
     *
     * @return array<int, array<string, mixed>> filtered data
     */
    public function filter(array $data): array
    {
        $filtered = [];

        foreach ($data as $row) {
            if ($this->passesFilter($row)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * Checks if the given data satisfies filters.
     *
     * @param array<string, mixed> $row data
     */
    private function passesFilter(array $row): bool
    {
        foreach ($row as $name => $value) {
            if (isset($this->rules[$name])) {
                // check all rules for a given attribute
                foreach ($this->rules[$name] as $rule) {
                    if (!$rule->match($value)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
