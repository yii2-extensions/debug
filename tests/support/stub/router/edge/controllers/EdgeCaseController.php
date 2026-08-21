<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\edge\controllers;

use yii\web\Controller;

/**
 * Controller fixture with Unicode, static, and non-public action-shaped methods.
 */
final class EdgeCaseController extends Controller
{
    public static function actionStatic(): bool
    {
        return true;
    }

    public function actionVisible(): bool
    {
        return true;
    }

    public function actionÁrbol(): bool
    {
        return true;
    }

    protected function actionProtected(): bool
    {
        return true;
    }
}
