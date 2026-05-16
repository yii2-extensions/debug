<?php

declare(strict_types=1);

namespace yii\debug\tests\router\controllers;

use Yii;
use yii\web\{Controller, ErrorAction};

final class RedirectController extends Controller
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
        return ['test' => ErrorAction::class];
    }

    public function init(): void
    {
        Yii::$app->response->redirect('web/first');
    }
}
