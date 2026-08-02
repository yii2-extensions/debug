<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use stdClass;
use Stringable;
use yii\debug\storage\{DebugValue, HydrationException};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DebugValue} covering the tagged capture of arbitrary PHP values, the guard rails that keep the
 * payload JSON-safe, and the strict hydration of every tagged type.
 *
 * @since 0.2
 */
#[Group('storage')]
final class DebugValueTest extends TestCase
{
    public function testCaptureFallsBackToTheClassNameWhenStringConversionThrows(): void
    {
        $value = DebugValue::capture(
            new class implements Stringable {
                public function __toString(): string
                {
                    throw new RuntimeException('cannot stringify');
                }
            },
        );

        self::assertSame('object', $value->type, 'The value stays a tagged object.');
        self::assertStringContainsString('Stringable@anonymous', (string) $value->value, 'The class name is the fallback.');
    }
    public function testCaptureLabelsAClosedResourceAsUnsupported(): void
    {
        $handle = fopen('php://memory', 'r');

        self::assertIsResource($handle, 'The fixture must open a stream.');

        fclose($handle);

        $value = DebugValue::capture($handle);

        self::assertSame('unsupported', $value->type, 'A closed resource is no longer a resource.');
        self::assertSame('unknown-type', $value->reason, 'The reason must record why it was rejected.');
    }

    public function testCaptureLabelsAnOpenResourceWithItsType(): void
    {
        $handle = fopen('php://memory', 'r');

        self::assertIsResource($handle, 'The fixture must open a stream.');

        $value = DebugValue::capture($handle);

        fclose($handle);

        self::assertSame('resource', $value->type, 'An open resource keeps its tagged type.');
        self::assertSame('stream', $value->resourceType, 'The resource type must be recorded.');
        self::assertSame('(resource: stream)', $value->toDisplayValue(), 'Display value must name the resource.');
        self::assertSame(
            ['type' => 'resource', 'resourceType' => 'stream'],
            $value->jsonSerialize(),
            'Serialized form must carry the resource type.',
        );
        self::assertEquals(
            $value,
            DebugValue::fromArray($value->jsonSerialize()),
            'A resource must round-trip through hydration.',
        );
    }

    public function testCaptureLabelsAThrowableWithItsMessage(): void
    {
        $value = DebugValue::capture(new RuntimeException('boom'));

        self::assertStringContainsString('boom', (string) $value->value, 'The label must carry the message.');
    }

    public function testCaptureStringifiesAStringableObject(): void
    {
        $value = DebugValue::capture(
            new class implements Stringable {
                public function __toString(): string
                {
                    return 'rendered';
                }
            },
        );

        self::assertSame('rendered', $value->value, 'A Stringable must be labelled with its string form.');
    }

    public function testCaptureTruncatesBeyondTheDepthLimit(): void
    {
        $deep = 'leaf';

        for ($i = 0; $i < 12; $i++) {
            $deep = [$deep];
        }

        self::assertStringContainsString(
            'DEEP NESTED VALUE',
            self::flatten(DebugValue::capture($deep)),
            'Values nested past the depth limit must be truncated.',
        );
    }

    public function testCaptureTruncatesBeyondTheNodeLimit(): void
    {
        self::assertStringContainsString(
            'SKIPPED over 10000 nodes',
            self::flatten(DebugValue::capture(range(1, 10_050))),
            'Values past the node budget must be truncated.',
        );
    }

    public function testHydrationRejectsFieldsThatDoNotBelongToTheTaggedType(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.value');

        DebugValue::fromArray(['type' => 'null', 'value' => null]);
    }

    public function testHydrationRejectsInvalidBinaryData(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.data');

        DebugValue::fromArray(['type' => 'binary', 'encoding' => 'base64', 'data' => '*invalid*']);
    }

    public function testHydrationRejectsUnknownFields(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.unexpected');

        DebugValue::fromArray(['type' => 'null', 'unexpected' => true]);
    }
    public function testRoundTripPreservesJsonSafeValuesAndLabelsUnsafeValues(): void
    {
        $object = new stdClass();
        $object->name = 'debug';
        $object->self = $object;

        $value = DebugValue::capture([
            'binary' => "\xB1\x31",
            'nan' => NAN,
            'positiveInfinity' => INF,
            'negativeInfinity' => -INF,
            'closure' => static fn(): bool => true,
            'object' => $object,
        ]);

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        $display = DebugValue::fromArray($decoded)->toDisplayValue();

        self::assertIsArray($display);
        self::assertIsString($display['binary'] ?? null);
        self::assertStringStartsWith('(binary: base64 ', $display['binary']);
        self::assertSame('NAN', $display['nan'] ?? null);
        self::assertSame('INF', $display['positiveInfinity'] ?? null);
        self::assertSame('-INF', $display['negativeInfinity'] ?? null);
        self::assertSame(['__class' => \Closure::class], $display['closure'] ?? null);

        $capturedObject = $display['object'] ?? null;

        self::assertIsArray($capturedObject);
        self::assertSame(stdClass::class, $capturedObject['__class'] ?? null);
        self::assertSame('debug', $capturedObject['name'] ?? null);
        self::assertSame(stdClass::class, $capturedObject['self'] ?? null);
    }

    public function testThrowHydrationExceptionForAnEntryKeyThatDoesNotMatchItsKeyType(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('.key');

        DebugValue::fromArray(
            [
                'type' => 'array',
                'entries' => [['keyType' => 'int', 'key' => 'not-an-int', 'value' => ['type' => 'null']]],
            ],
        );
    }

    public function testThrowHydrationExceptionForAnUnknownSpecialFloat(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.value');

        DebugValue::fromArray(['type' => 'special-float', 'value' => 'NOPE']);
    }

    public function testThrowHydrationExceptionForAnUnsupportedBinaryEncoding(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.encoding');

        DebugValue::fromArray(['type' => 'binary', 'encoding' => 'hex', 'data' => 'ff']);
    }

    /**
     * Renders the tagged value as a string so truncation labels can be asserted regardless of nesting depth.
     */
    private static function flatten(DebugValue $value): string
    {
        return (string) json_encode($value->jsonSerialize());
    }
}
