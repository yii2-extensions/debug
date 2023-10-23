<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\components\search\matchers;

use Yii;
use yii\helpers\VarDumper;

use function is_scalar;
use function mb_stripos;
use function mb_strtoupper;
use function strcmp;

/**
 * Checks if the given value is exactly or partially same as the base one.
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 *
 * @since 2.0
 */
class SameAs extends Base
{
    /**
     * @var bool if partial match should be used.
     */
    public bool $partial = false;

    public function match(mixed $value): bool
    {
        if (!is_scalar($value)) {
            $value = VarDumper::export($value);
        }

        if ($this->partial) {
            return mb_stripos($value, $this->baseValue, 0, Yii::$app->charset) !== false;
        }

        return strcmp(
            mb_strtoupper((string) $this->baseValue, Yii::$app->charset),
            mb_strtoupper((string) $value, Yii::$app->charset)
        ) === 0;
    }
}
