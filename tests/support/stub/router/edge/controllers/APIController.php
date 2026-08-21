<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\edge\controllers;

use yii\web\Controller;

/**
 * Acronym-named controller fixture used to verify strict controller ID normalization.
 */
final class APIController extends Controller
{
    public function actionIndex(): bool
    {
        return true;
    }
}
