<?php

declare(strict_types=1);

namespace yii\debug\tests\router\controllers;

use yii\rest\ActiveController;

final class RestController extends ActiveController
{
    public $modelClass = 'app\models\User';
}
