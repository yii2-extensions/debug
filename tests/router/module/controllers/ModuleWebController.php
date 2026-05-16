<?php

declare(strict_types=1);

namespace yii\debug\tests\router\module\controllers;

use yii\web\Controller;

class ModuleWebController extends Controller
{
    public function actionInside(): bool
    {
        return true;
    }
}
