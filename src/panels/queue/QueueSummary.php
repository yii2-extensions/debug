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
     * @return list<string> Component ids, deduplicated while preserving order.
     */
    public function componentIds(): array
    {
        $ids = [];

        foreach ($this->records as $record) {
            $ids[] = $record->componentId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Builds a queue summary from the captured job events.
     *
     * @param list<JobRecord> $records Captured job events.
     */
    public static function fromRecords(array $records): self
    {
        return new self($records);
    }

    /**
     * Returns whether at least one captured event reported a failure.
     */
    public function hasErrors(): bool
    {
        return $this->totalErrors() > 0;
    }

    /**
     * Returns whether the summary carries no records.
     */
    public function isEmpty(): bool
    {
        return $this->records === [];
    }

    /**
     * Returns the number of error events captured.
     */
    public function totalErrors(): int
    {
        return $this->countBy(JobRecord::TYPE_ERROR);
    }

    /**
     * Returns the total number of captured events across every lifecycle phase.
     */
    public function totalEvents(): int
    {
        return count($this->records);
    }

    /**
     * Returns the number of successfully executed events.
     */
    public function totalExecuted(): int
    {
        return $this->countBy(JobRecord::TYPE_EXEC);
    }

    /**
     * Returns the number of push events captured.
     */
    public function totalPushed(): int
    {
        return $this->countBy(JobRecord::TYPE_PUSH);
    }

    /**
     * Returns the number of records whose `eventType` equals `$eventType`.
     */
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
