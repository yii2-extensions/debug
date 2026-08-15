<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\HistorySummary;

/**
 * Unit tests for {@see HistorySummary} covering the manifest aggregation that feeds the History index summary header
 * total requests, per-bucket counts/sample codes/variants and the unique status-code filter map.
 */
#[Group('panel')]
#[Group('history')]
final class HistorySummaryTest extends TestCase
{
    public function testFromManifestBucketsByStatusRange(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                $this->summary(200),
                $this->summary(201),
                $this->summary(304),
                $this->summary(404),
                $this->summary(500),
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

    public function testFromManifestCountsTypedEntries(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                $this->summary(200),
                $this->summary(404),
            ],
        );

        self::assertSame(
            2,
            $summary->totalRequests,
            'Total count must reflect every typed manifest entry.',
        );
        self::assertCount(
            2,
            $summary->statusBuckets,
            'Each status family must contribute one bucket.',
        );
    }

    public function testFromManifestExposesEmptyFilterWhenNoStatusCaptured(): void
    {
        $summary = HistorySummary::fromManifest(
            [],
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
                $this->summary(201),
                $this->summary(200),
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

    public function testFromManifestMapsBucketsToVocabularyStatusClasses(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                $this->summary(200),
                $this->summary(301),
                $this->summary(404),
                $this->summary(500),
            ],
        );

        $variants = [];

        foreach ($summary->statusBuckets as $bucket) {
            $variants[$bucket->label] = $bucket->variant;
        }

        self::assertSame(
            [
                '2xx' => '2xx',
                '3xx' => '3xx',
                '4xx' => '4xx',
                '5xx' => '5xx',
            ],
            $variants,
            'Bucket variants must equal their status-class labels.',
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

    public function testFromManifestSkipsRequestsWithStatusBelow200(): void
    {
        $summary = HistorySummary::fromManifest(
            [
                $this->summary(100),
                $this->summary(200),
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
                $this->summary(404),
                $this->summary(200),
                $this->summary(200),
                $this->summary(302),
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

    private function summary(int $statusCode): RequestSummary
    {
        return new RequestSummary(
            tag: 'tag-' . $statusCode,
            url: 'https://example.test',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: $statusCode,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: null,
        );
    }
}
