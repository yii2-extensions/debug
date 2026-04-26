<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\web\Controller;

class WebController extends Controller
{
    public function actionFirst(): bool
    {
        return true;
    }

    /**
     * @return array<string, array{class: class-string}|class-string>
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => \yii\web\ErrorAction::class,
            ],
            'errorStraight' => \yii\web\ErrorAction::class,
        ];
    }

    public function actionSecond(): bool
    {
        return true;
    }

    public function someMethod(): bool
    {
        return true;
    }
}
