<?php

declare(strict_types=1);

namespace yii\debug\storage;

use PHPForge\Debug\Storage\RequestSummary;

/**
 * Carries the committed manifest and the summaries evicted by a snapshot write.
 */
final readonly class SnapshotWriteResult
{
    /**
     * @param array<string, RequestSummary>|null $entries Committed entries, or `null` when a legacy follow-up read
     * failed.
     * @param list<RequestSummary> $removed Entries evicted from the manifest.
     */
    public function __construct(
        public array|null $entries,
        public array $removed,
    ) {}
}
