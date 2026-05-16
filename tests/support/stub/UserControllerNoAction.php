<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\base\Action;
use yii\debug\controllers\UserController;

/**
 * Stub controller that extends the `UserController` but does not have any actions to test the behavior of the debug
 * module when a controller does not have any actions.
 */
final class UserControllerNoAction extends UserController
{
    public function createAction($id): Action|null
    {
        return null;
    }
}
