<?php

declare(strict_types=1);

namespace yiiunit\debug\router\controllers;

use yii\web\Controller;

class BadController extends Controller
{
    public function actionOnly(): bool
    {
        return true;
    }

    /**
     * Intentionally returns malformed entries to exercise the debug router's resilience to bad
     * action configs.
     *
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        return ['test' => 'Something not important'];
    }

    public function init(): void
    {
        throw new \Exception('Simulates problem with controller when initialing');
    }
}
