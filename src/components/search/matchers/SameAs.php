<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

use yii\helpers\VarDumper;
use Yii;

use function is_scalar;

/**
 * Checks if the given value is exactly or partially same as the base one.
 */
class SameAs extends Base
{
    /**
     * @var bool if partial match should be used.
     */
    public $partial = false;

    private ?string $baseValueUpper = null;

    public function match($value)
    {
        if (!is_scalar($value)) {
            $value = VarDumper::export($value);
        }

        $charset = Yii::$app->charset;

        if ($this->partial) {
            return mb_stripos($value, $this->baseValue, 0, $charset) !== false;
        }

        if ($this->baseValueUpper === null) {
            $this->baseValueUpper = mb_strtoupper($this->baseValue, $charset);
        }

        return strcmp($this->baseValueUpper, mb_strtoupper($value, $charset)) === 0;
    }
}
