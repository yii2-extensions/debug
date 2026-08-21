<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\storage\PayloadTest} test cases.
 *
 * Provides invalid decoded values, strict read descriptors, and expected hydration messages.
 */
final class PayloadProvider
{
    /**
     * @return iterable<string, array{
     *     0: mixed,
     *     1: non-empty-string,
     *     2: list<string>,
     *     3: string
     * }>
     */
    public static function hydrationExceptionCases(): iterable
    {
        yield 'object with integer keys' => [
            [1 => 'a'],
            'object',
            [],
            "Invalid debug snapshot value at '\$': expected an object with string keys.",
        ];
        yield 'non-boolean value' => [
            ['flag' => 1],
            'bool',
            ['flag'],
            "Invalid debug snapshot value at '\$.flag': expected a boolean.",
        ];
        yield 'non-integer value' => [
            ['count' => '7'],
            'int',
            ['count'],
            "Invalid debug snapshot value at '\$.count': expected an integer.",
        ];
        yield 'non-list value' => [
            ['entries' => ['a' => 1]],
            'list',
            ['entries'],
            "Invalid debug snapshot value at '\$.entries': expected a list.",
        ];
        yield 'non-number value' => [
            ['time' => '1.5'],
            'number',
            ['time'],
            "Invalid debug snapshot value at '$.time': expected a number.",
        ];
        yield 'non-string value' => [
            ['name' => 42],
            'string',
            ['name'],
            "Invalid debug snapshot value at '\$.name': expected a string.",
        ];
        yield 'nullable integer carrying a string' => [
            ['line' => '7'],
            'nullableInt',
            ['line'],
            "Invalid debug snapshot value at '$.line': expected an integer or null.",
        ];
        yield 'nullable number carrying a string' => [
            ['duration' => '1.5'],
            'nullableNumber',
            ['duration'],
            "Invalid debug snapshot value at '$.duration': expected a number or null.",
        ];
        yield 'nullable string carrying an integer' => [
            ['action' => 42],
            'nullableString',
            ['action'],
            "Invalid debug snapshot value at '$.action': expected a string or null.",
        ];
        yield 'undeclared field' => [
            ['name' => 'a', 'extra' => 1],
            'shape',
            ['name'],
            "Invalid debug snapshot value at '\$.extra': expected a declared field.",
        ];
        yield 'value that is not an object' => [
            ['a', 'b'],
            'object',
            [],
            "Invalid debug snapshot value at '\$': expected an object.",
        ];
        yield 'missing key read' => [
            [],
            'raw',
            ['absent'],
            "Invalid debug snapshot value at '\$.absent': expected a required field.",
        ];
        yield 'required field missing' => [
            [],
            'shape',
            ['name'],
            "Invalid debug snapshot value at '\$.name': expected a required field.",
        ];
    }
}
