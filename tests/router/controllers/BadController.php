<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\web\Controller;

class BadController extends Controller
{
    public function actionOnly()
    {
        return true;
    }

    public function actions()
    {
        return ['test' => 'Something not important'];
    }
    public function init()
    {
        throw new \Exception('Simulates problem with controller when initialing');
    }
}
