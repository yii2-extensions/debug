<?php

declare(strict_types=1);

namespace yiiunit\debug\router\module\controllers;

use yii\web\Controller;

class ModuleWebController extends Controller
{
    public function actionInside()
    {
        return true;
    }
}
