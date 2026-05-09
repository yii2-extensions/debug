<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use yii\log\Logger;

use function is_array;
use function is_numeric;

/**
 * Computes the typed {@see LogCounts} totals (errors / warnings / info) from the raw `$panel->data['messages']`
 * payload of {@see \yii\debug\panels\LogPanel}.
 *
 * Iterates the positional log tuples directly (the second element is the level) so the summary header shows totals
 * across every captured message regardless of the search-model filter applied to the grid.
 *
 * Usage example:
 * ```php
 * $counts = LogCountsNormalizer::fromPanelData($panel->data);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class LogCountsNormalizer
{
    public static function fromPanelData(mixed $data): LogCounts
    {
        $payload = is_array($data) ? $data : [];

        $messages = $payload['messages'] ?? null;

        if (!is_array($messages)) {
            return new LogCounts(0, 0, 0, 0);
        }

        $total = 0;
        $errors = 0;
        $warnings = 0;
        $info = 0;

        foreach ($messages as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $total++;

            $rawLevel = $entry[1] ?? null;
            $level = is_numeric($rawLevel) ? (int) $rawLevel : 0;

            match ($level) {
                Logger::LEVEL_ERROR => $errors++,
                Logger::LEVEL_WARNING => $warnings++,
                Logger::LEVEL_INFO => $info++,
                default => null,
            };
        }

        return new LogCounts($total, $errors, $warnings, $info);
    }
}
