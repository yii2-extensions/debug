<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

/**
 * Typed view-model for the log-level totals shown in the detail view's summary header.
 *
 * Counts are computed over the raw `$panel->data['messages']` payload (positional log tuples) rather than the typed
 * grid models, so the summary spans every captured row independently of the search filter.
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

    /**
     * Returns whether at least one `error`-level message was captured.
     */
    public function hasErrors(): bool
    {
        return $this->errors > 0;
    }

    /**
     * Returns whether at least one `info`-level message was captured.
     */
    public function hasInfo(): bool
    {
        return $this->info > 0;
    }

    /**
     * Returns whether at least one `warning`-level message was captured.
     */
    public function hasWarnings(): bool
    {
        return $this->warnings > 0;
    }
}
