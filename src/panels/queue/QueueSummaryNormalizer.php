<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use function is_array;

/**
 * Computes the typed {@see QueueSummary} from the raw `$panel->data['records']` payload of
 * {@see \yii\debug\panels\QueuePanel}.
 *
 * Usage example:
 * ```php
 * $summary = QueueSummaryNormalizer::fromPanelData($panel->data);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueueSummaryNormalizer
{
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
