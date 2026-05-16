<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Model;

/**
 * Filter-model stub whose `search()` returns a non-{@see \yii\data\DataProviderInterface} value.
 */
final class NonProviderFilterModel extends Model
{
    /**
     * @param array<int|string, mixed> $params Filter parameters.
     */
    public function search(array $params): string
    {
        return 'not-a-data-provider';
    }
}
