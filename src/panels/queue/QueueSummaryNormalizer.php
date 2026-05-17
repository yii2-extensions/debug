<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use function is_array;

/**
 * Computes the typed {@see QueueSummary} from the raw `$panel->data['records']` payload of {@see \yii\debug\panels\QueuePanel}.
 */
final class QueueSummaryNormalizer
{
    /**
     * Builds a {@see QueueSummary} from the raw panel payload, treating malformed entries as an empty summary.
     *
     * @param mixed $data Raw value of {@see \yii\debug\panels\QueuePanel::$data}.
     */
    public static function fromPanelData(mixed $data): QueueSummary
    {
        $payload = is_array($data) ? $data : [];

        $rawRecords = $payload['records'] ?? null;

        if (!is_array($rawRecords)) {
            return new QueueSummary([]);
        }

        $records = [];

        foreach ($rawRecords as $row) {
            $records[] = JobRecordNormalizer::from($row);
        }

        return new QueueSummary($records);
    }
}
