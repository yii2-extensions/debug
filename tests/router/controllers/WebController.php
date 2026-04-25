<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\web\Controller;

class WebController extends Controller
{
    public function actionFirst()
    {
        return true;
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'errorStraight' => 'yii\web\ErrorAction',
        ];
    }

    public function actionSecond()
    {
        return true;
    }

    public function someMethod()
    {
        return true;
    }
}
