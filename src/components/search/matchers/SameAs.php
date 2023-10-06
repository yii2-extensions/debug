<?php

declare(strict_types=1);

namespace yii\debug\components\search\matchers;

use Yii;
use yii\helpers\VarDumper;

use function is_scalar;
use function mb_stripos;
use function mb_strtoupper;
use function strcmp;

class SameAs extends Base
{
    /**
     * @var bool if partial match should be used.
     */
    public bool $partial = false;

    /**
     * {@inheritdoc}
     */
    public function match(mixed $value): bool
    {
        if (!is_scalar($value)) {
            $value = VarDumper::export($value);
        }
        if ($this->partial) {
            return mb_stripos($value, $this->baseValue, 0, Yii::$app->charset) !== false;
        }

        return strcmp(
            mb_strtoupper($this->baseValue, Yii::$app->charset),
            mb_strtoupper($value, Yii::$app->charset)
        ) === 0;
    }
}
