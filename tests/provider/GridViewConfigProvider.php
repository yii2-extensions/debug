<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\support\GridViewConfigTest} test cases.
 */
final class GridViewConfigProvider
{
    /**
     * @return iterable<string, array{0: string|null, 1: array<string, mixed>}>
     */
    public static function rowClassCases(): iterable
    {
        yield 'danger' => ['danger', ['class' => 'yii-debug-row-danger']];
        yield 'empty string returns empty array' => ['', []];
        yield 'error alias collapses to danger' => ['error', ['class' => 'yii-debug-row-danger']];
        yield 'info' => ['info', ['class' => 'yii-debug-row-info']];
        yield 'null returns empty array' => [null, []];
        yield 'success' => ['success', ['class' => 'yii-debug-row-success']];
        yield 'unknown level returns empty array' => ['exotic', []];
        yield 'warning' => ['warning', ['class' => 'yii-debug-row-warning']];
    }
}
