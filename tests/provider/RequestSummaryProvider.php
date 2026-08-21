<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

/**
 * Data provider for {@see \yii\debug\tests\storage\RequestSummaryTest} test cases.
 *
 * Provides valid decoded request-summary payloads and invalid variants with expected hydration messages.
 */
final class RequestSummaryProvider
{
    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function hydrationCases(): iterable
    {
        yield 'canonical request summary' => [self::payload()];
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function hydrationExceptionCases(): iterable
    {
        $payload = self::payload();
        $payload['statusCode'] = '200';

        yield 'numeric status string' => [
            $payload,
            '$.summary.statusCode',
        ];

        $payload = self::payload();
        $payload['mailCount'] = 2;
        $payload['mailFiles'] = ['a.eml', 42];

        yield 'non-string mail file' => [
            $payload,
            "Invalid debug snapshot value at '\$.summary.mailFiles[1]'",
        ];

        $payload = self::payload();
        $payload['unexpected'] = true;

        yield 'unknown field' => [
            $payload,
            '$.summary.unexpected',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
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
