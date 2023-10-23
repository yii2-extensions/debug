<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\components\search;

use yii\base\Component;
use yii\debug\components\search\matchers\MatcherInterface;

/**
 * Provides array filtering capabilities.
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 * @since 2.0
 */
class Filter extends Component
{
    /**
     * @var array rules for matching filters in the way: [:fieldName => [rule1, rule2,..]].
     */
    protected array $rules = [];

    /**
     * Adds data filtering rule.
     */
    public function addMatcher(string $name, MatcherInterface $rule): void
    {
        if ($rule->hasValue()) {
            $this->rules[$name][] = $rule;
        }
    }

    /**
     * Applies filter on a given array and returns filtered data.
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
     */
    private function passesFilter(array $row): bool
    {
        foreach ($row as $name => $value) {
            if (isset($this->rules[$name])) {
                // check all rules for a given attribute
                foreach ($this->rules[$name] as $rule) {
                    /* @var $rule MatcherInterface */
                    if (!$rule->match($value)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
