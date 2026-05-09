<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

/**
 * Typed view-model for the log-level totals shown in the detail view summary header.
 *
 * Counts are computed over the raw `$panel->data['messages']` payload (positional log tuples) rather than the typed
 * grid models, because the summary spans all rows independently of the search filter.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class LogCounts
{
    public function __construct(
        /**
         * Total number of log messages captured for the request.
         */
        public int $total,
        /**
         * Number of messages at `Logger::LEVEL_ERROR`.
         */
        public int $errors,
        /**
         * Number of messages at `Logger::LEVEL_WARNING`.
         */
        public int $warnings,
        /**
         * Number of messages at `Logger::LEVEL_INFO`.
         */
        public int $info,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors > 0;
    }

    public function hasInfo(): bool
    {
        return $this->info > 0;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings > 0;
    }
}
