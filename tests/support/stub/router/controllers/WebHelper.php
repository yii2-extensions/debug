<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\controllers;

use yii\web\Controller;

/**
 * Stub controller class in a file without the `Controller.php` suffix, so the route scan must skip it by filename.
 */
final class WebHelper extends Controller
{
    public function actionHidden(): bool
    {
        return true;
    }
}
