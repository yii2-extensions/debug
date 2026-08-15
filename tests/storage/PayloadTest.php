<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{HydrationException, Payload};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Payload} covering the strict type guards applied at the decoded-JSON boundary.
 *
 * @since 0.2
 */
#[Group('storage')]
final class PayloadTest extends TestCase
{
    public function testAllReturnsEveryDecodedEntry(): void
    {
        self::assertSame(
            ['a' => 1, 'b' => 'two'],
            Payload::object(['a' => 1, 'b' => 'two'])->all(),
            'Every decoded entry must survive.',
        );
    }

    public function testNullableNumberReturnsIntegerInputAsFloat(): void
    {
        self::assertSame(
            7.0,
            Payload::object(['duration' => 7])->nullableNumber('duration'),
            'Integer JSON numbers must satisfy the nullable float contract.',
        );
    }

    public function testObjectAcceptsAnEmptyArrayAsAnEmptyObject(): void
    {
        self::assertSame([], Payload::object([])->all(), 'An empty JSON object decodes to an empty array.');
    }

    public function testRowsValidatesEveryElementAsAnObject(): void
    {
        self::assertSame(
            [['file' => 'a.php'], ['file' => 'b.php']],
            Payload::object(['trace' => [['file' => 'a.php'], ['file' => 'b.php']]])->rows('trace'),
            'Each element must round-trip as a string-keyed map.',
        );
    }

    public function testThrowHydrationExceptionForAnObjectWithIntegerKeys(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$': expected an object with string keys.",
        );

        Payload::object([1 => 'a']);
    }

    public function testThrowHydrationExceptionForANonBooleanValue(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.flag': expected a boolean.",
        );

        Payload::object(['flag' => 1])->bool('flag');
    }

    public function testThrowHydrationExceptionForANonIntegerValue(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.count': expected an integer.",
        );

        Payload::object(['count' => '7'])->int('count');
    }

    public function testThrowHydrationExceptionForANonListValue(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.entries': expected a list.",
        );

        Payload::object(['entries' => ['a' => 1]])->list('entries');
    }

    public function testThrowHydrationExceptionForANonNumberValue(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.time': expected a number.",
        );

        Payload::object(['time' => '1.5'])->number('time');
    }

    public function testThrowHydrationExceptionForANonStringValue(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.name': expected a string.",
        );

        Payload::object(['name' => 42])->string('name');
    }

    public function testThrowHydrationExceptionForANullableIntegerCarryingAString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.line': expected an integer or null.",
        );

        Payload::object(['line' => '7'])->nullableInt('line');
    }

    public function testThrowHydrationExceptionForANullableNumberCarryingAString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.duration': expected a number or null.",
        );

        Payload::object(['duration' => '1.5'])->nullableNumber('duration');
    }

    public function testThrowHydrationExceptionForANullableStringCarryingAnInteger(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.action': expected a string or null.",
        );

        Payload::object(['action' => 42])->nullableString('action');
    }

    public function testThrowHydrationExceptionForAnUndeclaredField(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.extra': expected a declared field.",
        );

        Payload::object(['name' => 'a', 'extra' => 1])->shape(['name']);
    }

    public function testThrowHydrationExceptionForAValueThatIsNotAnObject(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$': expected an object.",
        );

        Payload::object(['a', 'b']);
    }

    public function testThrowHydrationExceptionWhenAMissingKeyIsRead(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.absent': expected a required field.",
        );

        Payload::object([])->raw('absent');
    }

    public function testThrowHydrationExceptionWhenARequiredFieldIsMissing(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.name': expected a required field.",
        );

        Payload::object([])->shape(['name']);
    }
}
