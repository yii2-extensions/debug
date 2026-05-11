<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

/**
 * Typed view-model for the Request panel hero header — the leading line that shows the HTTP method, the full request
 * URL, the response status pill, and the meta strip (`ip` / `time` / `durationMs` / boolean flags).
 *
 * Boolean flags are pre-formatted as their display labels (`'AJAX'`, `'PJAX'`, `'Flash'`, `'HTTPS'`) so the renderer
 * doesn't need to reproduce the mapping in HTML.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class RequestHero
{
    public function __construct(
        /**
         * HTTP method (`GET`, `POST`, ...). Empty when neither the panel data nor the request summary carry it.
         */
        public string $method,
        /**
         * Full request URL including the scheme and host (e.g. `http://example.test/index.php`).
         */
        public string $url,
        /**
         * Response status code; `0` when not captured. The renderer skips the status pill when this is `0`.
         */
        public int $statusCode,
        /**
         * Status-pill CSS modifier (`success` / `muted` / `warning` / `danger`) derived from `$statusCode`.
         */
        public string $statusVariant,
        /**
         * Caller IP address; empty when not captured.
         */
        public string $ip,
        /**
         * Formatted timestamp (`HH:MM:SS`); empty when the request summary did not carry a time.
         */
        public string $time,
        /**
         * Formatted processing duration (`'X.X ms'`); empty when the summary did not carry a processing time.
         */
        public string $durationMs,
        /**
         * Display labels for the boolean flags active on this request, in registration order.
         *
         * @var list<string>
         */
        public array $flags,
    ) {}
}
