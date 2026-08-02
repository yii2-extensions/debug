<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use yii\debug\storage\RequestSummary;

use function date;

/**
 * Typed view-model for one captured-request row in the History GridView.
 *
 * Projects the manifest's {@see RequestSummary} into the shape the grid renders, adding only the pre-formatted clock
 * time; every value is already typed, so no narrowing happens per cell.
 */
final readonly class HistoryRow
{
    public function __construct(
        /**
         * Request tag (hex hash); empty when not captured.
         */
        public string $tag,
        /**
         * HTTP method ('GET', 'POST', ...) or 'COMMAND' for console runs. Empty when not captured.
         */
        public string $method,
        /**
         * Full request URL. Empty when not captured.
         */
        public string $url,
        /**
         * Response status code; `0` when not captured.
         */
        public int $statusCode,
        /**
         * Request timestamp (unix seconds, may be float for sub-second precision); '0.0' when not captured.
         */
        public float $time,
        /**
         * Formatted compact time ('HH:MM:SS'); empty when `$time === 0.0`.
         */
        public string $timeCompact,
        /**
         * Processing time in seconds (float); `null` when not captured.
         */
        public float|null $processingTime,
        /**
         * Peak memory in bytes; `null` when not captured.
         */
        public int|null $peakMemory,
        /**
         * Client IP address. Empty when not captured.
         */
        public string $ip,
        /**
         * Number of SQL queries executed during the request. '0' when not captured.
         */
        public int $sqlCount,
        /**
         * Number of mail messages sent during the request. '0' when not captured.
         */
        public int $mailCount,
        /**
         * Number of callers issuing too many DB calls. '0' when not flagged.
         */
        public int $excessiveCallersCount,
        /**
         * `true` when the captured request was an AJAX request.
         */
        public bool $ajax,
    ) {}

    /**
     * Projects a manifest entry into the row the History grid renders.
     */
    public static function fromSummary(RequestSummary $summary): self
    {
        return new self(
            tag: $summary->tag,
            method: $summary->method,
            url: $summary->url,
            statusCode: $summary->statusCode,
            time: $summary->time,
            timeCompact: $summary->time > 0 ? date('H:i:s', (int) $summary->time) : '',
            processingTime: $summary->processingTime,
            peakMemory: $summary->peakMemory,
            ip: $summary->ip,
            sqlCount: $summary->sqlCount,
            mailCount: $summary->mailCount,
            excessiveCallersCount: $summary->excessiveCallersCount,
            ajax: $summary->ajax,
        );
    }
}
