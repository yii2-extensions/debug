<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\GridViewConfigTest;

/**
 * Data provider for {@see GridViewConfigTest} test cases.
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
        yield 'leading whitespace is not trimmed' => [' error', []];
        yield 'mixed-case alias remains unknown' => ['Error', []];
        yield 'null byte remains unknown' => ["danger\0", []];
        yield 'null returns empty array' => [null, []];
        yield 'numeric string remains unknown' => ['0', []];
        yield 'success' => ['success', ['class' => 'yii-debug-row-success']];
        yield 'trailing alias whitespace is not trimmed' => ['error ', []];
        yield 'trailing level whitespace is not trimmed' => ['warning ', []];
        yield 'unknown level returns empty array' => ['exotic', []];
        yield 'unsupported CSS variant remains unknown' => ['primary', []];
        yield 'uppercase alias remains unknown' => ['ERROR', []];
        yield 'uppercase danger remains unknown' => ['DANGER', []];
        yield 'uppercase info remains unknown' => ['INFO', []];
        yield 'uppercase success remains unknown' => ['SUCCESS', []];
        yield 'uppercase warning remains unknown' => ['WARNING', []];
        yield 'warning' => ['warning', ['class' => 'yii-debug-row-warning']];
        yield 'whitespace remains unknown' => [' ', []];
    }
}
