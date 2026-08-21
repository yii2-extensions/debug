<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use LogicException;
use PHPForge\Debug\Storage\{ExceptionSnapshot, HydrationException};
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ExceptionSnapshot} covering its round-trip through JSON and hydration of invalid payloads.
 */
#[Group('storage')]
final class ExceptionSnapshotTest extends TestCase
{
    public function testHydrationRejectsInvalidCodeType(): void
    {
        $payload = ExceptionSnapshot::fromThrowable(new RuntimeException('failure'))
            ->jsonSerialize();

        $payload['code'] = false;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.exception.code',
        );

        ExceptionSnapshot::fromArray($payload);
    }

    public function testThrowableRoundTripsThroughJson(): void
    {
        $throwable = new RuntimeException('outer failure', 42, new LogicException('inner failure', 7));

        $snapshot = ExceptionSnapshot::fromThrowable($throwable);

        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        $hydrated = ExceptionSnapshot::fromArray($decoded);

        self::assertSame(
            RuntimeException::class,
            $hydrated->getClass(),
            'The hydrated exception must retain its original class.',
        );
        self::assertSame(
            'outer failure',
            $hydrated->getMessage(),
            'The hydrated exception must retain its original message.',
        );
        self::assertSame(
            42,
            $hydrated->getCode(),
            'The hydrated exception must retain its original code.',
        );
        self::assertSame(
            $throwable->getFile(),
            $hydrated->getFile(),
            'The hydrated exception must retain its original file.',
        );
        self::assertSame(
            $throwable->getLine(),
            $hydrated->getLine(),
            'The hydrated exception must retain its original line.',
        );
        self::assertSame(
            (string) $throwable,
            (string) $hydrated,
            'The hydrated exception must retain its original string representation.',
        );

        $frame = $hydrated->getTrace()[0] ?? self::fail('Expected the helper call in the captured trace.');

        self::assertSame(
            ['namespace', 'short_class', 'class', 'type', 'function', 'file', 'line', 'args'],
            array_keys($frame),
            'Trace projection must retain frame metadata alongside its arguments.',
        );

        $previous = $hydrated->getPrevious();

        self::assertNotNull(
            $previous,
            'The hydrated exception must retain its previous exception.',
        );
        self::assertSame(
            LogicException::class,
            $previous->getClass(),
            'The hydrated previous exception must retain its original class.',
        );
        self::assertSame(
            'inner failure',
            $previous->getMessage(),
            'The hydrated previous exception must retain its original message.',
        );
        self::assertSame(
            7,
            $previous->getCode(),
            'The hydrated previous exception must retain its original code.',
        );
    }
}
