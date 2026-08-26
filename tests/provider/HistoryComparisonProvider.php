<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\widgets\history\HistoryComparisonTest} test cases.
 */
final class HistoryComparisonProvider
{
    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function distinctEscapedPaths(): iterable
    {
        yield 'slash key vs nested key' => [['a/b' => 1], ['a' => ['b' => 1]]];

        yield 'plain key vs slash key' => [['ab' => 1], ['a/b' => 1]];

        yield 'tilde sequence vs slash key' => [['a~0b' => 1], ['a/b' => 1]];
    }
}
