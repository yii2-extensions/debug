<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Action;
use yii\debug\controllers\UserController;

/**
 * Controller stub with no actions.
 */
final class UserControllerNoAction extends UserController
{
    public function createAction($id): Action|null
    {
        return null;
    }
}
