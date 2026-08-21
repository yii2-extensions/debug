<?php

declare(strict_types=1);

namespace yii\debug\tests\storage;

use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see RequestSummary} covering the strict type guards applied at the decoded-JSON boundary.
 */
#[Group('storage')]
final class RequestSummaryTest extends TestCase
{
    public function testJsonPayloadHydratesWithoutScalarCoercion(): void
    {
        $summary = RequestSummary::fromArray($this->payload());

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

    public function testNumericStringIsRejected(): void
    {
        $payload = $this->payload();

        $payload['statusCode'] = '200';

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.summary.statusCode',
        );

        RequestSummary::fromArray($payload);
    }

    public function testThrowHydrationExceptionWhenAMailFileEntryIsNotAString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.summary.mailFiles[1]'",
        );

        RequestSummary::fromArray(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 2,
                'mailFiles' => ['a.eml', 42],
                'processingTime' => null,
                'peakMemory' => null,
            ],
        );
    }

    public function testUnknownFieldIsRejected(): void
    {
        $payload = $this->payload();
        $payload['unexpected'] = true;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.summary.unexpected');

        RequestSummary::fromArray($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'tag' => 'tag-1',
            'url' => 'https://example.test/',
            'ajax' => false,
            'method' => 'GET',
            'ip' => '127.0.0.1',
            'time' => 1_700_000_000.0,
            'statusCode' => 200,
            'sqlCount' => 0,
            'excessiveCallersCount' => 0,
            'mailCount' => 0,
            'mailFiles' => [],
            'processingTime' => null,
            'peakMemory' => null,
        ];
    }
}
