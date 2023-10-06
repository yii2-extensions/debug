<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\rest\ActiveController;

class RestController extends ActiveController
{
    public $modelClass = 'app\models\User';
}
