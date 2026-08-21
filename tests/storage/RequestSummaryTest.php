<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use yii\debug\tests\provider\RequestSummaryProvider;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see RequestSummary} covering the strict type guards applied at the decoded-JSON boundary.
 *
 * {@see RequestSummaryProvider} for test case data providers.
 */
#[Group('storage')]
final class RequestSummaryTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProviderExternal(RequestSummaryProvider::class, 'hydrationCases')]
    public function testJsonPayloadHydratesWithoutScalarCoercion(array $payload): void
    {
        $summary = RequestSummary::fromArray($payload);

        self::assertSame(
            200,
            $summary->statusCode,
            'The status code must round-trip.',
        );
        self::assertSame(
            1_700_000_000.0,
            $summary->time,
            'The time must round-trip.',
        );
        self::assertFalse(
            $summary->ajax,
            'The ajax flag must round-trip.',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProviderExternal(RequestSummaryProvider::class, 'hydrationExceptionCases')]
    public function testThrowHydrationExceptionForInvalidSummaryPayload(
        array $payload,
        string $exceptionMessage,
    ): void {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage($exceptionMessage);

        RequestSummary::fromArray($payload);
    }
}
