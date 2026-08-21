<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\storage\DebugValueTest} test cases.
 *
 * Provides invalid tagged values and expected hydration-message fragments.
 */
final class DebugValueProvider
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function hydrationExceptionCases(): iterable
    {
        yield 'field outside tagged type' => [
            ['type' => 'null', 'value' => null],
            '$.value',
        ];
        yield 'invalid binary data' => [
            ['type' => 'binary', 'encoding' => 'base64', 'data' => '*invalid*'],
            '$.data',
        ];
        yield 'unknown field' => [
            ['type' => 'null', 'unexpected' => true],
            '$.unexpected',
        ];
        yield 'entry key does not match key type' => [
            [
                'type' => 'array',
                'entries' => [['keyType' => 'int', 'key' => 'not-an-int', 'value' => ['type' => 'null']]],
            ],
            '.key',
        ];
        yield 'unknown special float' => [
            ['type' => 'special-float', 'value' => 'NOPE'],
            '$.value',
        ];
        yield 'unsupported binary encoding' => [
            ['type' => 'binary', 'encoding' => 'hex', 'data' => 'ff'],
            '$.encoding',
        ];
    }
}
