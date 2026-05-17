<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

use function array_key_exists;
use function date;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function sprintf;

/**
 * Narrows the loosely-typed `$panel->data` payload (alongside the controller summary array) into the typed
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
     * Narrows `$panel->data` plus the controller `$summary` into the typed {@see RequestView}.
     *
     * @param mixed $data Raw value of {@see \yii\debug\panels\RequestPanel::$data}.
     * @param array<string, mixed> $summary Controller summary block accompanying the panel data.
     */
    public static function fromPanelData(mixed $data, array $summary): RequestView
    {
        $data = is_array($data) ? $data : [];

        $hero = self::buildHero($data, $summary);
        $tabs = self::buildTabs($data);

        return new RequestView(hero: $hero, tabs: $tabs);
    }

    /**
     * Narrows a `mixed` saved-payload bucket into a name → value array, falling back to `[]` for non-array buckets.
     *
     * @return array<int|string, mixed> Bucket entries preserving the original keys.
     */
    private static function asEntries(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Coerces the value to an int, falling back to `0` when it is neither an int nor a numeric string.
     */
    private static function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Returns the value when it is already a string, falling back to `''` otherwise.
     */
    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Builds the hero header view-model from the panel data and the controller summary.
     *
     * @param array<int|string, mixed> $data Panel data narrowed to an array.
     * @param array<string, mixed> $summary Controller summary block.
     */
    private static function buildHero(array $data, array $summary): RequestHero
    {
        $statusCode = self::asInt($data['statusCode'] ?? $summary['statusCode'] ?? 0);

        $general = is_array($data['general'] ?? null) ? $data['general'] : [];

        $method = self::asString($general['method'] ?? $summary['method'] ?? '');
        $url = self::asString($summary['url'] ?? '');
        $ip = self::asString($summary['ip'] ?? '');

        $timeValue = $summary['time'] ?? null;

        $time = is_numeric($timeValue) && $timeValue !== '0' && $timeValue !== 0 && $timeValue !== 0.0
            ? date('H:i:s', (int) $timeValue)
            : '';

        $processing = $summary['processingTime'] ?? null;

        $durationMs = is_numeric($processing)
            ? sprintf('%.1f ms', (float) $processing * 1000)
            : '';

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
     * Resolves the status-pill CSS modifier (`success` / `muted` / `warning` / `danger`) for the given HTTP status.
     */
    private static function statusVariant(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'danger',
            $statusCode >= 400 => 'warning',
            $statusCode >= 300 => 'muted',
            $statusCode >= 200 => 'success',
            default => 'muted',
        };
    }
}
