<?php

declare(strict_types=1);

namespace yii\debug\components\search;

use yii\base\Component;
use yii\debug\components\search\matchers\MatcherInterface;

/**
 * Filters tabular arrays by attribute-matching rules.
 *
 * Rules are registered with {@see addMatcher()} keyed by attribute name; {@see filter()} returns the subset of rows
 * that satisfy every rule attached to each present attribute.
 */
class Filter extends Component
{
    /**
     * @var array<string, array<int, MatcherInterface>> Matchers indexed by attribute name, in registration order.
     */
    protected array $rules = [];

    /**
     * Registers a matcher rule for the given attribute.
     *
     * Rules whose {@see MatcherInterface::hasValue()} returns `false` are silently skipped.
     *
     * @param string $name Attribute name the rule applies to.
     * @param MatcherInterface $rule Matcher rule to attach.
     */
    public function addMatcher(string $name, MatcherInterface $rule): void
    {
        if ($rule->hasValue()) {
            $this->rules[$name][] = $rule;
        }
    }

    /**
     * Returns the subset of rows that satisfy every registered rule.
     *
     * @param array<int, array<string, mixed>> $data Rows to filter.
     *
     * @return array<int, array<string, mixed>> Rows that passed all matchers, reindexed sequentially.
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
     * Returns whether the row passes every matcher attached to a present attribute.
     *
     * @param array<string, mixed> $row Row to check.
     */
    private function passesFilter(array $row): bool
    {
        foreach ($row as $name => $value) {
            if (isset($this->rules[$name])) {
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
