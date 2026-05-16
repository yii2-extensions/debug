<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use function count;
use function is_array;
use function is_numeric;
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
     * Builds the typed summary from the raw manifest array.
     *
     * @param array<int|string, mixed> $manifest
     */
    public static function fromManifest(array $manifest): self
    {
        $totalRequests = count($manifest);

        $buckets = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
        $sample = [];
        $codes = [];

        foreach ($manifest as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $statusCode = is_numeric($entry['statusCode'] ?? null) ? (int) $entry['statusCode'] : 0;

            if ($statusCode > 0) {
                $codes[$statusCode] = $statusCode;
            }

            $bucket = match (true) {
                $statusCode >= 500 && $statusCode < 600 => '5xx',
                $statusCode >= 400 && $statusCode < 500 => '4xx',
                $statusCode >= 300 && $statusCode < 400 => '3xx',
                $statusCode >= 200 && $statusCode < 300 => '2xx',
                default => null,
            };

            if ($bucket === null) {
                continue;
            }

            $buckets[$bucket]++;
            $sample[$bucket] ??= $statusCode;
        }

        $variants = ['2xx' => 'success', '3xx' => 'info', '4xx' => 'warn', '5xx' => 'danger'];
        $statusBuckets = [];

        foreach ($buckets as $label => $count) {
            if ($count === 0) {
                continue;
            }

            $statusBuckets[] = new HistoryStatusBucket(
                label: $label,
                count: $count,
                sampleCode: $sample[$label] ?? 0,
                variant: $variants[$label],
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
