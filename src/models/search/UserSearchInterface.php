<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

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
     * Creates data provider instance with search query applied.
     *
     * @param array<int|string, mixed> $params the data array to load model.
     */
    public function search(array $params): DataProviderInterface;
}
