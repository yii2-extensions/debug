<?php

declare(strict_types=1);

namespace yii\debug\tests\helpers;

use PHPUnit\Framework\Attributes\Group;
use Stringable;
use yii\debug\helpers\Coerce;
use yii\debug\tests\support\TestCase;

/**
 * Tests mixed-value normalization performed by {@see Coerce}.
 *
 * Test coverage.
 * - Converts numeric and string values with configured defaults.
 * - Accepts scalar and {@see Stringable} values for nullable strings.
 * - Filters malformed array keys and trace frames.
 */
#[Group('helpers')]
#[Group('coerce')]
final class CoerceTest extends TestCase
{
    public function testFloatAcceptsNumericValuesAndUsesConfiguredDefault(): void
    {
        self::assertSame(12.5, Coerce::float('12.5'));
        self::assertSame(7.5, Coerce::float([], 7.5));
        self::assertNull(Coerce::floatOrNull([]));
    }

    public function testIntAcceptsNumericValuesAndUsesConfiguredDefault(): void
    {
        self::assertSame(12, Coerce::int('12.9'));
        self::assertSame(7, Coerce::int([], 7));
        self::assertNull(Coerce::intOrNull([]));
    }

    public function testStringAcceptsOnlyStringsAndUsesConfiguredDefault(): void
    {
        self::assertSame('debug', Coerce::string('debug'));
        self::assertSame('fallback', Coerce::string(42, 'fallback'));
    }

    public function testStringKeyedArrayDropsIntegerKeysAndPreservesOrder(): void
    {
        self::assertSame(
            ['first' => 1, 'second' => 3],
            Coerce::stringKeyedArray(['first' => 1, 2, 'second' => 3]),
            'Integer keys must be dropped and order preserved.',
        );
    }

    public function testStringListKeepsOnlyStringEntriesAndFallsBackOnNonArray(): void
    {
        self::assertSame(
            ['kept-a', 'kept-b'],
            Coerce::stringList(['kept-a', 42, null, 'kept-b']),
            'Only string entries must survive, in order.',
        );
        self::assertSame([], Coerce::stringList('not-an-array'), 'Non-array input must collapse to `[]`.');
    }

    public function testStringOrNullAcceptsScalarsAndStringableObjects(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        self::assertSame('42', Coerce::stringOrNull(42));
        self::assertSame('stringable', Coerce::stringOrNull($stringable));
        self::assertNull(Coerce::stringOrNull([]));
    }

    public function testTraceFramesDropsMalformedFramesAndIntegerKeys(): void
    {
        self::assertSame(
            [
                ['file' => '/app/index.php', 'line' => 10],
                ['function' => 'run'],
            ],
            Coerce::traceFrames(
                [
                    ['file' => '/app/index.php', 'line' => 10, 0 => 'drop'],
                    'drop',
                    ['function' => 'run'],
                ],
            ),
        );
        self::assertSame([], Coerce::traceFrames('invalid'));
    }
}
