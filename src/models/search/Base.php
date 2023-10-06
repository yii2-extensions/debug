<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\base\Model;
use yii\debug\components\search\Filter;
use yii\debug\components\search\matchers;

use function str_contains;
use function str_replace;

/**
 * Base search model
 */
class Base extends Model
{
    /**
     * Adds filtering condition for a given attribute
     *
     * @param Filter $filter filter instance
     * @param string $attribute attribute to filter
     * @param bool $partial if partial match should be used
     */
    public function addCondition(Filter $filter, string $attribute, bool $partial = false): void
    {
        $value = (string)$this->$attribute;

        if (str_contains($value, '>')) {
            $value = (int) str_replace('>', '', $value);
            $filter->addMatcher($attribute, new matchers\GreaterThan(['value' => $value]));
        } elseif (str_contains($value, '<')) {
            $value = (int)str_replace('<', '', $value);
            $filter->addMatcher($attribute, new matchers\LowerThan(['value' => $value]));
        } else {
            $filter->addMatcher($attribute, new matchers\SameAs(['value' => $value, 'partial' => $partial]));
        }
    }
}
