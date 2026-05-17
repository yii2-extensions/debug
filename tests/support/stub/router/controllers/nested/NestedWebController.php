<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\controllers\nested;

use yii\web\Controller;

/**
 * Stub controller for testing nested controller routing.
 */
final class NestedWebController extends Controller
{
    public function actionShow(): bool
    {
        return true;
    }
}
