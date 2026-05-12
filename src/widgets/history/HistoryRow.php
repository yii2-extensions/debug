<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use function date;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * Typed view-model for one captured-request row in the History GridView.
 *
 * Encapsulates the loose `array<string, mixed>` entry produced by {@see \yii\debug\models\search\Debug::search()} into
 * a `final readonly` shape so the GridView column closures stay free of {@see is_array()} / {@see is_numeric()}
 * narrowing on every cell access.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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
        public bool $isAjax,
    ) {}

    /**
     * Narrows the loose array shape into a typed row.
     *
     * @param array<int|string, mixed> $row
     */
    public static function from(array $row): self
    {
        $time = self::asFloat($row['time'] ?? 0.0);

        return new self(
            tag: self::asString($row['tag'] ?? ''),
            method: self::asString($row['method'] ?? ''),
            url: self::asString($row['url'] ?? ''),
            statusCode: self::asInt($row['statusCode'] ?? 0),
            time: $time,
            timeCompact: $time > 0 ? date('H:i:s', (int) $time) : '',
            processingTime: is_numeric($row['processingTime'] ?? null) ? (float) $row['processingTime'] : null,
            peakMemory: is_numeric($row['peakMemory'] ?? null) ? (int) $row['peakMemory'] : null,
            ip: self::asString($row['ip'] ?? ''),
            sqlCount: self::asInt($row['sqlCount'] ?? 0),
            mailCount: self::asInt($row['mailCount'] ?? 0),
            excessiveCallersCount: self::asInt($row['excessiveCallersCount'] ?? 0),
            isAjax: self::isTruthy($row['ajax'] ?? false),
        );
    }

    /**
     * Narrows a `mixed` value into a typed `HistoryRow`, accepting either an already-typed instance or an array
     * payload produced by the data provider.
     */
    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::from(is_array($value) ? $value : []);
    }

    private static function asFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function asInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function isTruthy(mixed $value): bool
    {
        return $value !== false && $value !== null && $value !== 0 && $value !== '' && $value !== '0';
    }
}
