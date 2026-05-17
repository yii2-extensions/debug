<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\module\controllers;

use yii\web\Controller;

/**
 * Stub controller for testing the router with a controller that throws an exception in {@see init()} and simulating a
 * scenario where `getModule()` returns `null` for a child module.
 */
class ModuleWebController extends Controller
{
    public function actionInside(): bool
    {
        return true;
    }
}
