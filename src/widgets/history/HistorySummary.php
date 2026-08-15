<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\Storage\RequestSummary;

use function count;
use function ksort;

/**
 * Typed aggregate view-model for the History index summary header.
 *
 * Pre-computes the captured-request total, the per-status-bucket counts + sample codes, and the unique status-code list
 * consumed by the GridView's status filter dropdown.
 */
final readonly class HistorySummary
{
    public function __construct(
        /**
         * Total number of captured requests in the manifest.
         */
        public int $totalRequests,
        /**
         * Non-empty status buckets in display order; empty when no captured request mapped into a known bucket.
         *
         * @var list<HistoryStatusBucket>
         */
        public array $statusBuckets,
        /**
         * Unique status-code map (`code => code`) consumed by the GridView's status filter dropdown; `null` when the
         * manifest has no captured statuses (the dropdown collapses to a text input).
         *
         * @var array<int|string, int|string>|null
         */
        public array|null $statusCodeFilter,
    ) {}

    /**
     * Builds the typed summary from the request manifest.
     *
     * @param array<array-key, RequestSummary> $manifest Manifest entries; only the values are read.
     */
    public static function fromManifest(array $manifest): self
    {
        $totalRequests = count($manifest);

        $buckets = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
        $sample = [];
        $codes = [];

        foreach ($manifest as $entry) {
            $statusCode = $entry->statusCode;

            if ($statusCode > 0) {
                $codes[$statusCode] = $statusCode;
            }

            if ($statusCode < 200 || $statusCode >= 600) {
                continue;
            }

            $bucket = match (true) {
                $statusCode >= 500 => '5xx',
                $statusCode >= 400 => '4xx',
                $statusCode >= 300 => '3xx',
                default => '2xx',
            };

            $buckets[$bucket]++;
            $sample[$bucket] ??= $statusCode;
        }

        $statusBuckets = [];

        foreach ($buckets as $label => $count) {
            if (!isset($sample[$label])) {
                continue;
            }

            $statusBuckets[] = new HistoryStatusBucket(
                label: $label,
                count: $count,
                sampleCode: $sample[$label],
                variant: $label,
            );
        }

        ksort($codes);

        return new self(
            totalRequests: $totalRequests,
            statusBuckets: $statusBuckets,
            statusCodeFilter: $codes === [] ? null : $codes,
        );
    }
}
