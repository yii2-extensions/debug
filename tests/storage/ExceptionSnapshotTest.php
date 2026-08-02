<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use LogicException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use yii\debug\storage\{ExceptionSnapshot, HydrationException};
use yii\debug\tests\support\TestCase;

#[Group('storage')]
final class ExceptionSnapshotTest extends TestCase
{
    public function testHydrationRejectsInvalidCodeType(): void
    {
        $payload = ExceptionSnapshot::fromThrowable(new RuntimeException('failure'))->jsonSerialize();
        $payload['code'] = false;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.exception.code');

        ExceptionSnapshot::fromArray($payload);
    }

    public function testThrowableRoundTripsThroughJson(): void
    {
        $throwable = new RuntimeException('outer failure', 42, new LogicException('inner failure', 7));
        $snapshot = ExceptionSnapshot::fromThrowable($throwable);
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        $hydrated = ExceptionSnapshot::fromArray($decoded);

        self::assertSame(RuntimeException::class, $hydrated->getClass());
        self::assertSame('outer failure', $hydrated->getMessage());
        self::assertSame(42, $hydrated->getCode());
        self::assertSame($throwable->getFile(), $hydrated->getFile());
        self::assertSame($throwable->getLine(), $hydrated->getLine());
        self::assertSame((string) $throwable, (string) $hydrated);
        $previous = $hydrated->getPrevious();

        self::assertNotNull($previous);
        self::assertSame(LogicException::class, $previous->getClass());
        self::assertSame('inner failure', $previous->getMessage());
    }
}
