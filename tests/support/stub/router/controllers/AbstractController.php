<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\router\controllers;

use yii\web\Controller;

/**
 * Abstract controller fixture used to verify route discovery rejects non-instantiable controllers.
 */
abstract class AbstractController extends Controller {}
