<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use yii\debug\storage\{DebugArray, DebugValue};

/**
 * Unit tests for {@see DebugArray} covering the array-typed facade over {@see DebugValue}.
 *
 * @since 0.2
 */
#[Group('storage')]
final class DebugArrayTest extends TestCase
{
    public function testRoundTripsNestedValues(): void
    {
        $captured = DebugArray::capture(['a' => 1, 'b' => ['c' => true]]);

        self::assertSame(
            ['a' => 1, 'b' => ['c' => true]],
            DebugArray::fromArray($captured->jsonSerialize(), '$.panels.config.data')->values(),
            'Nested values must survive the round-trip.',
        );
    }

    public function testThrowHydrationExceptionWhenTheTaggedValueIsNotAnArray(): void
    {
        $this->expectExceptionMessage("Invalid debug snapshot value at '\$.panels.config.data': expected a tagged array.");

        DebugArray::fromArray(DebugValue::capture('a string')->jsonSerialize(), '$.panels.config.data');
    }
}
