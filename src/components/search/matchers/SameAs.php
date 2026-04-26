<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

use Yii;
use yii\helpers\VarDumper;

use function is_scalar;

/**
 * Checks if the given value is exactly or partially same as the base one.
 */
class SameAs extends Base
{
    /**
     * If partial match should be used.
     */
    public bool $partial = false;

    /**
     * Uppercase version of base value for case-insensitive comparison.
     */
    private string|null $baseValueUpper = null;

    /**
     * Checks if the value passed matches base value.
     *
     * @param mixed $value Value to be matched.
     *
     * @return bool `true` if the value passed matches base value, `false` otherwise.
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
