<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use function array_unique;
use function array_values;
use function count;

/**
 * Typed aggregate view-model for the Queue panel detail view.
 *
 * Bundles the per-event records with the totals shown in the summary header (pushed / executed / failed) and the list
 * of distinct queue component ids surfaced as tabs when more than one queue was active during the request.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class QueueSummary
{
    public function __construct(
        /**
         * @var list<JobRecord> Captured events in chronological order.
         */
        public array $records,
    ) {}

    /**
     * Returns the distinct component ids surfaced across every captured event, in first-seen order.
     *
     * @return list<string>
     */
    public function componentIds(): array
    {
        $ids = [];

        foreach ($this->records as $record) {
            $ids[] = $record->componentId;
        }

        return array_values(array_unique($ids));
    }

    public function hasErrors(): bool
    {
        return $this->totalErrors() > 0;
    }

    public function isEmpty(): bool
    {
        return $this->records === [];
    }

    /**
     * Filters the records down to a single component id (used by the tab UI to scope the rendered cards).
     *
     * @return list<JobRecord>
     */
    public function recordsForComponent(string $componentId): array
    {
        $filtered = [];

        foreach ($this->records as $record) {
            if ($record->componentId === $componentId) {
                $filtered[] = $record;
            }
        }

        return $filtered;
    }

    public function totalErrors(): int
    {
        return $this->countBy('error');
    }

    public function totalEvents(): int
    {
        return count($this->records);
    }

    public function totalExecuted(): int
    {
        return $this->countBy('exec');
    }

    public function totalPushed(): int
    {
        return $this->countBy('push');
    }

    private function countBy(string $eventType): int
    {
        $count = 0;

        foreach ($this->records as $record) {
            if ($record->eventType === $eventType) {
                $count++;
            }
        }

        return $count;
    }
}
