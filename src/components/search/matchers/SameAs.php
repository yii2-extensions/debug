<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

use Yii;
use yii\helpers\VarDumper;

use function is_scalar;

/**
 * Matches candidate values exactly or partially against the configured base value.
 *
 * Comparison is multibyte-aware: partial mode uses case-insensitive substring search via {@see mb_stripos()}; exact
 * mode compares uppercased forms. Non-scalar values are normalized with {@see VarDumper::export()} before comparison.
 */
class SameAs extends Base
{
    /**
     * Whether partial (substring) matching is used instead of exact matching.
     */
    public bool $partial = false;

    /**
     * Cached uppercase form of the base value, computed lazily for case-insensitive exact comparison.
     */
    private string|null $baseValueUpper = null;

    /**
     * Returns whether the candidate value matches the base value under the active comparison mode.
     *
     * @param mixed $value Candidate value to test.
     *
     * @return bool `true` when the candidate satisfies the rule, `false` otherwise.
     */
    public function match(mixed $value): bool
    {
        $valueStr = is_scalar($value) ? (string) $value : VarDumper::export($value);
        $base = is_scalar($this->baseValue) ? (string) $this->baseValue : VarDumper::export($this->baseValue);

        $charset = Yii::$app->charset;

        if ($this->partial) {
            return mb_stripos($valueStr, $base, 0, $charset) !== false;
        }

        if ($this->baseValueUpper === null) {
            $this->baseValueUpper = mb_strtoupper($base, $charset);
        }

        return strcmp($this->baseValueUpper, mb_strtoupper($valueStr, $charset)) === 0;
    }
}
