<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\tests\ToolbarDataMapperTest;

/**
 * Data provider for {@see ToolbarDataMapperTest} test cases.
 */
final class ToolbarEnvelopeProvider
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformed(): iterable
    {
        yield 'items missing' => [
            ['title' => 'Broken'],
            'items',
        ];
        yield 'items not a list' => [
            ['title' => 'Broken', 'items' => ['first' => ['value' => 'ok']]],
            'items',
        ];
        yield 'items not an array' => [
            ['title' => 'Broken', 'items' => 'free-form'],
            'items',
        ];
        yield 'item not an array' => [
            ['title' => 'Broken', 'items' => [['value' => 'ok'], 'not-an-array']],
            'items[1]',
        ];
        yield 'item without value' => [
            ['title' => 'Broken', 'items' => [['label' => 'Count']]],
            'items[0].value',
        ];
        yield 'item with non-stringable value' => [
            ['title' => 'Broken', 'items' => [['value' => ['nested']]]],
            'items[0].value',
        ];
        yield 'non-stringable id' => [
            ['id' => [], 'title' => 'Broken', 'items' => [['value' => 'ok']]],
            'id',
        ];
        yield 'non-stringable title' => [
            ['title' => [], 'items' => [['value' => 'ok']]],
            'title',
        ];
    }
}
