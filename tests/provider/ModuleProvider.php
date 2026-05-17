<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\support\ModuleTest} test cases.
 */
final class ModuleProvider
{
    /**
     * @return iterable<int, array{0: list<string>, 1: string, 2: bool}>
     */
    public static function checkAccessCases(): iterable
    {
        yield [[], '10.20.30.40', false];
        yield [['10.20.30.40'], '10.20.30.40', true];
        yield [['*'], '10.20.30.40', true];
        yield [['10.20.30.*'], '10.20.30.40', true];
        yield [['10.20.30.*'], '10.20.40.40', false];
        yield [['172.16.0.0/12'], '172.15.1.2', false];
        yield [['172.16.0.0/12'], '172.16.0.0', true];
        yield [['172.16.0.0/12'], '172.22.33.44', true];
        yield [['172.16.0.0/12'], '172.31.255.255', true];
        yield [['172.16.0.0/12'], '172.32.1.2', false];
    }
}
