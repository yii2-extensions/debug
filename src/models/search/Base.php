<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\base\Model;
use yii\debug\components\search\{Filter, matchers};

use function is_scalar;

/**
 * Shared scaffolding for debug-panel search models, providing the operator-aware filter syntax used by every grid.
 *
 * Subclasses define their searchable attributes and {@see Model::rules()}; {@see addCondition()} parses the submitted
 * value to decide whether to attach a `>`, `<`, or exact/partial matcher to the supplied {@see Filter}.
 */
class Base extends Model
{
    /**
     * Attaches the matcher implied by the submitted value to the filter for the given attribute.
     *
     * Recognizes a leading `>` (numeric greater-than) and `<` (numeric lower-than) operator; otherwise attaches an
     * exact or partial {@see matchers\SameAs} matcher depending on `$partial`.
     *
     * @param Filter $filter Filter the matcher is added to.
     * @param string $attribute Attribute whose submitted value drives the matcher selection.
     * @param bool $partial `true` to use substring (case-insensitive) matching for non-operator values.
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
