<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\edge\controllers;

use yii\web\Controller;

/**
 * Non-controller-suffixed fixture placed before valid controller files during directory scans.
 */
final class AAHelper extends Controller
{
    public function actionHidden(): bool
    {
        return true;
    }
}
