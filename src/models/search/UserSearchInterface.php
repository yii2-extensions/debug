<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\DataProviderInterface;
use yii\web\IdentityInterface;

/**
 * Contract for identity models that also expose a search-form data provider to the User Switch panel.
 *
 * Implementations carry the regular {@see IdentityInterface} identity API and add a {@see search()} method that the
 * panel calls to render the table of impersonation candidates.
 */
interface UserSearchInterface extends IdentityInterface
{
    /**
     * Returns a data provider over the identity records, filtered by the submitted search parameters.
     *
     * @param array<int|string, mixed> $params Raw request parameters consumed by the implementing model.
     */
    public function search(array $params): DataProviderInterface;
}
