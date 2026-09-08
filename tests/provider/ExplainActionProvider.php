<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\actions\db\ExplainActionTest;

/**
 * Data provider for {@see ExplainActionTest} test cases.
 */
final class ExplainActionProvider
{
    /**
     * Sequence number stored by the fixture snapshot the rejected lookups run against.
     */
    public const int KNOWN_SEQ = 2;
    /**
     * Tag of the fixture snapshot the rejected lookups run against.
     */
    public const string KNOWN_TAG = 'tag-known';

    /**
     * @return iterable<string, array{0: mixed, 1: mixed, 2: int}>
     */
    public static function rejectedLookups(): iterable
    {
        yield 'empty seq' => ['', self::KNOWN_TAG, 400];
        yield 'negative seq' => ['-1', self::KNOWN_TAG, 400];
        yield 'decimal seq' => ['2.0', self::KNOWN_TAG, 400];
        yield 'array seq' => [['2'], self::KNOWN_TAG, 400];
        yield 'empty tag' => ['2', '', 400];
        yield 'array tag' => ['2', [self::KNOWN_TAG], 400];
        yield 'unknown tag' => ['2', 'tag-missing', 404];
        yield 'unknown seq' => ['9', self::KNOWN_TAG, 404];
        yield 'zero-padded seq' => ['02', self::KNOWN_TAG, 404];
    }
}
