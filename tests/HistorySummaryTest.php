<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\widgets\history\HistorySummary;

/**
 * Unit tests for {@see HistorySummary} covering the manifest aggregation that feeds the History index summary header
 * total requests, per-bucket counts/sample codes/variants and the unique status-code filter map.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('history')]
final class HistorySummaryTest extends TestCase
{
    public function testFromManifestBucketsByStatusRange(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                ['statusCode' => 200],
                ['statusCode' => 201],
                ['statusCode' => 304],
                ['statusCode' => 404],
                ['statusCode' => 500],
            ],
        );

        $counts = [];

        foreach ($summary->statusBuckets as $bucket) {
            $counts[$bucket->label] = $bucket->count;
        }

        self::assertSame(
            [
                '2xx' => 2,
                '3xx' => 1,
                '4xx' => 1,
                '5xx' => 1,
            ],
            $counts,
            'Bucket counts must reflect the manifest distribution.',
        );
    }

    public function testFromManifestExposesEmptyFilterWhenNoStatusCaptured(): void
    {
        $summary = HistorySummary::fromManifest(
            [['method' => 'GET']],
        );

        self::assertNull(
            $summary->statusCodeFilter,
            'Manifest without captured statuses must yield a null filter dropdown.',
        );
    }

    public function testFromManifestExposesFirstSeenSampleCode(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                ['statusCode' => 201],
                ['statusCode' => 200],
            ],
        );

        self::assertNotEmpty(
            $summary->statusBuckets,
            'Bucket list must be non-empty.',
        );
        self::assertSame(
            201,
            $summary->statusBuckets[0]->sampleCode,
            'Sample code must be the first observed in the bucket.',
        );
    }

    public function testFromManifestMapsBucketsToCssVariants(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                ['statusCode' => 200],
                ['statusCode' => 301],
                ['statusCode' => 404],
                ['statusCode' => 500],
            ],
        );

        $variants = [];

        foreach ($summary->statusBuckets as $bucket) {
            $variants[$bucket->label] = $bucket->variant;
        }

        self::assertSame(
            [
                '2xx' => 'success',
                '3xx' => 'info',
                '4xx' => 'warn',
                '5xx' => 'danger',
            ],
            $variants,
            'Bucket variants must follow the status code class mapping.',
        );
    }

    public function testFromManifestReturnsEmptyForEmptyManifest(): void
    {
        $summary = HistorySummary::fromManifest(
            [],
        );

        self::assertSame(
            0,
            $summary->totalRequests,
            'Empty manifest must yield zero total requests.',
        );
        self::assertSame(
            [],
            $summary->statusBuckets,
            'Empty manifest must yield no buckets.',
        );
        self::assertNull(
            $summary->statusCodeFilter,
            'Empty manifest must yield a null filter dropdown.',
        );
    }

    public function testFromManifestSkipsNonArrayManifestEntries(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                ['statusCode' => 200],
                'not-an-array',
                42,
                ['statusCode' => 404],
            ],
        );

        self::assertSame(
            4,
            $summary->totalRequests,
            'Total count must reflect every manifest entry.',
        );
        self::assertCount(
            2,
            $summary->statusBuckets,
            'Only array entries contribute to buckets.',
        );
    }

    public function testFromManifestSkipsRequestsWithStatusBelow200(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                ['statusCode' => 100],
                ['statusCode' => 200],
            ],
        );

        self::assertNotEmpty(
            $summary->statusBuckets,
            "Bucket list must surface the '200' entry.",
        );
        self::assertSame(
            1,
            $summary->statusBuckets[0]->count,
            "Status '100' must not contribute to any bucket."
        );
    }

    public function testFromManifestSortsUniqueStatusCodes(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                ['statusCode' => 404],
                ['statusCode' => 200],
                ['statusCode' => 200],
                ['statusCode' => 302],
            ],
        );

        self::assertSame(
            [
                200 => 200,
                302 => 302,
                404 => 404,
            ],
            $summary->statusCodeFilter,
            'Filter map must list unique status codes in ascending order.',
        );
    }
}
