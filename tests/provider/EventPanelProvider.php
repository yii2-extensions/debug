<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\event\EventPanelTest;

/**
 * Provides query variations for the unified event table in {@see EventPanelTest}.
 */
final class EventPanelProvider
{
    /**
     * @return iterable<string, array{array<string, mixed>, int, string, string}>
     */
    public static function queryControls(): iterable
    {
        yield 'descending order' => [
            ['sort' => '-time', 'per-page' => '1'],
            3,
            '+500.000 ms',
            '+375.000 ms gap',
        ];
        yield 'filtered observation' => [
            ['Event' => ['name' => 'third'], 'per-page' => '1'],
            3,
            '+500.000 ms',
            '+375.000 ms gap',
        ];
        yield 'second page' => [
            ['sort' => 'time', 'page' => '2', 'per-page' => '1'],
            2,
            '+125.000 ms',
            '+125.000 ms gap',
        ];
    }
}
