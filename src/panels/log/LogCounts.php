<?php

declare(strict_types=1);

namespace yii\debug\panels\log;

use yii\log\Logger;

use function count;

/**
 * Typed view-model for the log-level totals shown in the detail view's summary header.
 *
 * Counts span every captured row, independently of the search filter applied to the grid.
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
     * Builds log-level totals from the captured rows.
     *
     * @param list<LogRow> $rows Captured log rows.
     */
    public static function fromRows(array $rows): self
    {
        $errors = 0;
        $warnings = 0;
        $info = 0;

        foreach ($rows as $row) {
            match ($row->level) {
                Logger::LEVEL_ERROR => $errors++,
                Logger::LEVEL_WARNING => $warnings++,
                Logger::LEVEL_INFO => $info++,
                default => null,
            };
        }

        return new self(count($rows), $errors, $warnings, $info);
    }

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
