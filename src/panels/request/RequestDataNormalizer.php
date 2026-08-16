<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

use PHPForge\Debug\Helper\{Coerce, Vocabulary};
use PHPForge\Debug\Storage\RequestSummary;

use function array_key_exists;
use function date;
use function is_array;
use function sprintf;

/**
 * Narrows the request snapshot payload (alongside the controller summary array) into the typed
 * {@see RequestView} the detail view consumes.
 *
 * Concentrates every `is_array()` / `is_string()` / `is_int()` defensive check in one place, so the view stays focused
 * on rendering.
 */
final class RequestDataNormalizer
{
    /**
     * @var array<string, string> Boolean flags surfaced as chips on the hero meta strip, in display order.
     */
    private const array FLAG_LABELS = [
        'isAjax' => 'AJAX',
        'isPjax' => 'PJAX',
        'isFlash' => 'Flash',
        'isSecureConnection' => 'HTTPS',
    ];

    /**
     * Builds the typed {@see RequestView} from the captured request data and the manifest entry.
     *
     * @param array<array-key, mixed> $data Captured request data exposed by {@see RequestSnapshot}.
     * @param RequestSummary|null $summary Manifest entry for the loaded capture, when available.
     */
    public static function fromPanelData(array $data, RequestSummary|null $summary): RequestView
    {
        return new RequestView(hero: self::buildHero($data, $summary), tabs: self::buildTabs($data));
    }

    /**
     * Narrows a `mixed` snapshot bucket into a name → value array, falling back to `[]` for non-array buckets.
     *
     * @return array<int|string, mixed> Bucket entries preserving the original keys.
     */
    private static function asEntries(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Builds the hero header view-model from the panel data and the manifest entry.
     *
     * @param array<array-key, mixed> $data Captured request data.
     * @param RequestSummary|null $summary Manifest entry for the loaded capture, when available.
     */
    private static function buildHero(array $data, RequestSummary|null $summary): RequestHero
    {
        $statusCode = Coerce::intOrNull($data['statusCode'] ?? null)
            ?? ($summary === null ? 0 : $summary->statusCode);

        $general = is_array($data['general'] ?? null) ? $data['general'] : [];

        $method = Coerce::stringOrNull($general['method'] ?? null)
            ?? ($summary === null ? '' : $summary->method);
        $url = $summary === null ? '' : $summary->url;
        $ip = $summary === null ? '' : $summary->ip;

        $capturedAt = $summary === null ? 0.0 : $summary->time;

        $time = $capturedAt > 0 ? date('H:i:s', (int) $capturedAt) : '';

        $processing = $summary?->processingTime;

        $durationMs = $processing === null ? '' : sprintf('%.1f ms', $processing * 1000);

        $flags = [];

        foreach (self::FLAG_LABELS as $key => $label) {
            if (($general[$key] ?? false) === true) {
                $flags[] = $label;
            }
        }

        return new RequestHero(
            method: $method,
            url: $url,
            statusCode: $statusCode,
            statusVariant: self::statusVariant($statusCode),
            ip: $ip,
            time: $time,
            durationMs: $durationMs,
            flags: $flags,
        );
    }

    /**
     * Builds the tab list, conditionally including the Session and Server tabs based on the captured payload.
     *
     * The Session tab is only surfaced when the request actually had a session active, matching the legacy behavior
     * where only requests that touched `$_SESSION` exposed the panel section.
     *
     * @param array<int|string, mixed> $data Panel data narrowed to an array.
     *
     * @return list<RequestTab> Tabs in display order.
     */
    private static function buildTabs(array $data): array
    {
        $tabs = [
            new RequestTab(label: 'Parameters', sections: self::parameterSections($data)),
            new RequestTab(label: 'Headers', sections: self::headerSections($data)),
        ];

        if (array_key_exists('SESSION', $data) && array_key_exists('flashes', $data)) {
            $tabs[] = new RequestTab(
                label: 'Session',
                sections: self::sessionSections($data),
            );
        }

        if (array_key_exists('SERVER', $data)) {
            $tabs[] = new RequestTab(
                label: 'Server',
                sections: [
                    new RequestSection(
                        caption: 'Server',
                        entries: self::asEntries($data['SERVER']),
                        filterable: true,
                    ),
                ],
            );
        }

        return $tabs;
    }

    /**
     * Builds the sections rendered under the Headers tab (request + response headers).
     *
     * @param array<int|string, mixed> $data Panel data narrowed to an array.
     *
     * @return list<RequestSection> Header sections in display order.
     */
    private static function headerSections(array $data): array
    {
        return [
            new RequestSection(
                caption: 'Request Headers',
                entries: self::asEntries($data['requestHeaders'] ?? []),
                filterable: true,
            ),
            new RequestSection(
                caption: 'Response Headers',
                entries: self::asEntries($data['responseHeaders'] ?? []),
                filterable: true,
            ),
        ];
    }

    /**
     * Builds the sections rendered under the Parameters tab (routing, GET/POST/FILES/COOKIE, request body).
     *
     * @param array<int|string, mixed> $data Panel data narrowed to an array.
     *
     * @return list<RequestSection> Parameter sections in display order.
     */
    private static function parameterSections(array $data): array
    {
        $sections = [
            new RequestSection(
                caption: 'Routing',
                entries: [
                    'Route' => $data['route'] ?? null,
                    'Action' => $data['action'] ?? null,
                    'Parameters' => $data['actionParams'] ?? null,
                ],
            ),
        ];

        if (array_key_exists('GET', $data)) {
            $sections[] = new RequestSection(
                caption: 'Get',
                entries: self::asEntries($data['GET']),
            );
        }

        if (array_key_exists('POST', $data)) {
            $sections[] = new RequestSection(
                caption: 'Post',
                entries: self::asEntries($data['POST']),
            );
        }

        if (array_key_exists('FILES', $data)) {
            $sections[] = new RequestSection(
                caption: 'Files',
                entries: self::asEntries($data['FILES']),
            );
        }

        if (array_key_exists('COOKIE', $data)) {
            $sections[] = new RequestSection(
                caption: 'Cookies',
                entries: self::asEntries($data['COOKIE']),
            );
        }

        $sections[] = new RequestSection(
            caption: 'Request Body',
            entries: self::asEntries($data['requestBody'] ?? []),
        );

        return $sections;
    }

    /**
     * Builds the sections rendered under the Session tab (session data + flashes).
     *
     * @param array<int|string, mixed> $data Panel data narrowed to an array.
     *
     * @return list<RequestSection> Session sections in display order.
     */
    private static function sessionSections(array $data): array
    {
        return [
            new RequestSection(
                caption: 'Session',
                entries: self::asEntries($data['SESSION'] ?? []),
                filterable: true,
            ),
            new RequestSection(
                caption: 'Flashes',
                entries: self::asEntries($data['flashes'] ?? []),
            ),
        ];
    }

    /**
     * Resolves the status-pill vocabulary suffix (`2xx` / `3xx` / `4xx` / `5xx` / `none`) for the given HTTP status.
     */
    private static function statusVariant(int $statusCode): string
    {
        return Vocabulary::statusClass($statusCode);
    }
}
