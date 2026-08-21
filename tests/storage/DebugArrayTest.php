<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{DebugArray, DebugValue, HydrationException};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DebugArray} covering the array-typed facade over {@see DebugValue}.
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
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.panels.config.data': expected a tagged array.",
        );

        DebugArray::fromArray(DebugValue::capture('a string')->jsonSerialize(), '$.panels.config.data');
    }
}
