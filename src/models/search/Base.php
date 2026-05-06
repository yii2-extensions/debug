<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\models\search;

use yii\base\Model;
use yii\debug\components\search\Filter;
use yii\debug\components\search\matchers;

use function is_scalar;

/**
 * Base search model.
 */
class Base extends Model
{
    /**
     * Adds filtering condition for a given attribute.
     *
     * @param Filter $filter Filter instance.
     * @param string $attribute Attribute to filter.
     * @param bool $partial If partial match should be used.
     */
    public function addCondition(Filter $filter, string $attribute, bool $partial = false): void
    {
        $rawValue = $this->getAttributes([$attribute])[$attribute] ?? null;
        $value = is_scalar($rawValue) ? (string) $rawValue : '';

        if (mb_strpos($value, '>') !== false) {
            $value = (int) str_replace('>', '', $value);

            $filter->addMatcher($attribute, new matchers\GreaterThan(['value' => $value]));
        } elseif (mb_strpos($value, '<') !== false) {
            $value = (int) str_replace('<', '', $value);

            $filter->addMatcher($attribute, new matchers\LowerThan(['value' => $value]));
        } else {
            $filter->addMatcher($attribute, new matchers\SameAs(['value' => $value, 'partial' => $partial]));
        }
    }
}
