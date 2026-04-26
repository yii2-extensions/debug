<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\web\Controller;

class RedirectController extends Controller
{
    public function actionOnly(): bool
    {
        return true;
    }

    /**
     * @return array<string, class-string>
     */
    public function actions(): array
    {
        return ['test' => \yii\web\ErrorAction::class];
    }

    public function init(): void
    {
        \Yii::$app->response->redirect('web/first');
    }
}
