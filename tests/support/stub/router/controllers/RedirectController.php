<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\controllers;

use Override;
use Yii;
use yii\web\{Controller, ErrorAction};

/**
 * Stub controller that redirects in {@see init()} to simulate a problem with the controller when initializing.
 */
final class RedirectController extends Controller
{
    public function actionOnly(): bool
    {
        return true;
    }

    /**
     * @return array<string, class-string>
     */
    #[Override]
    public function actions(): array
    {
        return ['test' => ErrorAction::class];
    }

    #[Override]
    public function init(): void
    {
        Yii::$app->response->redirect('web/first');
    }
}
