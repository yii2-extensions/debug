<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\DataProviderInterface;

/**
 * Contract for filter models backing the User Switch panel's search form.
 *
 * Implementations expose a single {@see search()} method that returns a {@see DataProviderInterface} over the
 * candidate identities filtered by the submitted request parameters.
 */
interface UserSearchInterface
{
    /**
     * Returns a data provider over the identity records, filtered by the submitted search parameters.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by the implementing model.
     */
    public function search(array $params): DataProviderInterface;
}
