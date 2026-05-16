<?php

declare(strict_types=1);

namespace yii\debug\tests\router\controllers;

use Exception;
use yii\web\Controller;

final class BadController extends Controller
{
    public function actionOnly(): bool
    {
        return true;
    }

    /**
     * The router never reaches this body {@see init()} throws first so the returned payload is irrelevant to the
     * scenario under test. Kept type-compliant with the parent contract.
     *
     * @return array<array-key, array{class: class-string, ...}|class-string>
     */
    public function actions(): array
    {
        return [];
    }

    public function init(): void
    {
        throw new Exception('Simulates problem with controller when initialing');
    }
}
