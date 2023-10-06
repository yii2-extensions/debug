<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use yii\data\DataProviderInterface;
use yii\web\IdentityInterface;

/**
 * UserSearchInterface is the interface that should be implemented by a class providing identity information and search
 * method.
 */
interface UserSearchInterface extends IdentityInterface
{
    /**
     * Creates data provider instance with a search query applied.
     *
     * @param array $params the data array to load model.
     *
     * @return DataProviderInterface
     */
    public function search(array $params): DataProviderInterface;
}
