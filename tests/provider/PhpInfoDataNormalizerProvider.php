<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\PhpInfoDataNormalizerTest} test cases.
 *
 * Provides the leading header row of a data table paired with the label the head bar must show.
 */
final class PhpInfoDataNormalizerProvider
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function dataTableHeads(): iterable
    {
        yield 'contribution' => ['<th>Contribution</th><th>Authors</th>', 'Contributors'];
        yield 'module' => ['<th>Module</th><th>Authors</th>', 'Modules'];
        yield 'module name' => ['<th>Module Name</th><th>Authors</th>', 'Modules'];
        yield 'statistics' => ['<th>Statistics</th><th></th>', 'Statistics'];
        yield 'variable' => ['<th>Variable</th><th>Contents</th>', 'Variables'];
        yield 'unknown header' => ['<th>Anything</th><th>Value</th>', 'Data'];
    }
}
