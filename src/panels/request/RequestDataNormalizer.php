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
 * Concentrates every `is_array()` / `is_string()` / `is_int()` defensive check in one place so the view stays focused
 * on rendering and the PHPStan baseline can drop its `Cannot access offset ... on mixed` entries.
 *
 * Usage example:
 * ```php
 * $view = \yii\debug\panels\request\RequestDataNormalizer::fromPanelData($panel->data, $summary);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RequestDataNormalizer
{
    /**
     * Active boolean flags surfaced as chips on the hero meta strip. The map preserves display order.
     *
     * @var array<string, string>
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
     * @param array<string, mixed> $summary
     */
    public static function fromPanelData(mixed $data, array $summary): RequestView
    {
        $data = is_array($data) ? $data : [];

        $hero = self::buildHero($data, $summary);
        $tabs = self::buildTabs($data);

        return new RequestView(hero: $hero, tabs: $tabs);
    }

    /**
     * Narrows a `mixed` saved-payload bucket into a name → value array; non-array buckets fall back to empty.
     *
     * @return array<int|string, mixed>
     */
    private static function asEntries(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @param array<int|string, mixed> $data
     * @param array<string, mixed> $summary
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
     * @param array<int|string, mixed> $data
     *
     * @return list<RequestTab>
     */
    private static function buildTabs(array $data): array
    {
        $tabs = [
            new RequestTab(label: 'Parameters', sections: self::parameterSections($data)),
            new RequestTab(label: 'Headers', sections: self::headerSections($data)),
        ];

        // The Session tab is only surfaced when the request actually had a session active; matches the legacy behaviour
        // where only requests that touched `$_SESSION` exposed the panel section.
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
     * @param array<int|string, mixed> $data
     *
     * @return list<RequestSection>
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
     * @param array<int|string, mixed> $data
     *
     * @return list<RequestSection>
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
     * @param array<int|string, mixed> $data
     *
     * @return list<RequestSection>
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
