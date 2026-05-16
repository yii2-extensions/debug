<?php

declare(strict_types=1);

namespace yii\debug\tests\router\controllers;

use yii\web\{Controller, ErrorAction};

final class WebController extends Controller
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
                'class' => ErrorAction::class,
            ],
            'errorStraight' => ErrorAction::class,
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
