<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{HydrationException, Payload};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use yii\debug\tests\provider\PayloadProvider;

/**
 * Unit tests for {@see Payload} covering the strict type guards applied at the decoded-JSON boundary.
 *
 * {@see PayloadProvider} for test case data providers.
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
        self::assertSame(
            [],
            Payload::object([])->all(),
            'An empty JSON object decodes to an empty array.',
        );
    }

    public function testRowsValidatesEveryElementAsAnObject(): void
    {
        self::assertSame(
            [['file' => 'a.php'], ['file' => 'b.php']],
            Payload::object(['trace' => [['file' => 'a.php'], ['file' => 'b.php']]])->rows('trace'),
            'Each element must round-trip as a string-keyed map.',
        );
    }

    /**
     * @param non-empty-string $operation
     * @param list<string> $arguments
     */
    #[DataProviderExternal(PayloadProvider::class, 'hydrationExceptionCases')]
    public function testThrowHydrationExceptionForInvalidPayload(
        mixed $value,
        string $operation,
        array $arguments,
        string $exceptionMessage,
    ): void {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $payload = Payload::object($value);
        $key = $arguments[0] ?? '';

        match ($operation) {
            'bool' => $payload->bool($key),
            'int' => $payload->int($key),
            'list' => $payload->list($key),
            'nullableInt' => $payload->nullableInt($key),
            'nullableNumber' => $payload->nullableNumber($key),
            'nullableString' => $payload->nullableString($key),
            'number' => $payload->number($key),
            'object' => $payload,
            'raw' => $payload->raw($key),
            'shape' => $payload->shape($arguments),
            'string' => $payload->string($key),
            default => throw new \InvalidArgumentException('Unknown payload operation.'),
        };
    }
}
