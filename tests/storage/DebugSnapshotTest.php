<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{DebugSnapshot, HydrationException, PanelFailure, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see DebugSnapshot} covering its version guard and serialized panel failures.
 */
#[Group('storage')]
final class DebugSnapshotTest extends TestCase
{
    public function testJsonSerializeProjectsPanelFailuresToArrays(): void
    {
        $snapshot = new DebugSnapshot(
            self::summary(),
            [],
            [
                'log' => PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('boom')),
            ],
        );

        $failures = $snapshot->jsonSerialize()['failures'] ?? null;

        self::assertIsArray(
            $failures,
            'The failure collection must serialize as an array.',
        );

        $failure = $failures['log'] ?? null;

        self::assertIsArray(
            $failure,
            'Serialized failures must not expose storage objects.',
        );
        self::assertSame(
            PanelFailure::CAPTURE,
            $failure['stage'] ?? null,
            'The failure stage must be retained.',
        );

        $exception = $failure['exception'] ?? null;

        self::assertIsArray(
            $exception,
            'The exception must serialize as an array.',
        );
        self::assertSame(
            'boom',
            $exception['message'] ?? null,
            'The serialized exception payload must be retained.',
        );
    }

    public function testThrowHydrationExceptionWhenTheStorageVersionDoesNotMatch(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.version': expected storage version " . DebugSnapshot::VERSION . '.',
        );

        DebugSnapshot::fromArray(
            [
                'version' => DebugSnapshot::VERSION - 1,
                'summary' => [],
                'panels' => [],
                'failures' => [],
            ],
        );
    }

    private static function summary(): RequestSummary
    {
        return new RequestSummary(
            tag: 'tag-1',
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: null,
        );
    }
}
