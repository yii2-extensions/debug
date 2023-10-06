<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\web\Controller;

class RedirectController extends Controller
{
    public function init(): void
    {
        \Yii::$app->response->redirect('web/first');
    }

    public function actionOnly()
    {
        return true;
    }

    public function actions()
    {
        return ['test' => 'yii\web\ErrorAction'];
    }
}
