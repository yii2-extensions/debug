<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\controllers;

use yii\rest\ActiveController;

/**
 * Stub controller for testing REST controller routing.
 */
final class RestController extends ActiveController
{
    public $modelClass = 'app\models\User';
}
