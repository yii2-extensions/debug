<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

use UIAwesome\Html\Helper\Encode;

use function array_filter;
use function array_map;
use function array_values;
use function basename;
use function count;
use function explode;
use function function_exists;
use function getenv;
use function html_entity_decode;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strip_tags;
use function stripos;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

use const ENT_QUOTES;
use const PREG_SET_ORDER;

/**
 * Narrows the raw {@see phpinfo()} HTML output into the typed {@see PhpInfoView}.
 *
 * Splits the captured body into the overview chunk (everything before the first `<h2>`) and the modules chunk (every
 * `<h2>` + table that follows), parses each `<tr><td>k</td><td>v</td></tr>` pair into a key/value map, builds the five
 * hero sections ('PHP version' / 'Build' / 'Configuration' / 'Capabilities' / 'Streams') with typed tiles, and wraps
 * every module `<table>` in the panel's table chrome plus a deep-link `<section>` so the search filter and the TOC can
 * hide / jump to it.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class PhpInfoDataNormalizer
{
    /**
     * Builds the typed view-model from the raw {@see phpinfo()} HTML body, the runtime metrics PHP itself reports
     * (SAPI, OS, memory limit) and the active {@see PHP_VERSION} constant.
     */
    public static function fromOutput(string $body, string $phpVersion, string $sapi, string $os, string $memoryLimit): PhpInfoView
    {
        $home = self::resolveHomeDirectory();

        [$overviewSrc, $modulesSrc] = self::splitOverviewAndModules($body);

        $overviewRows = self::parseOverviewRows($overviewSrc);

        $tocEntries = [new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview')];

        $modulesHtml = self::wrapModulesHtml($modulesSrc, $tocEntries);
        $sections = self::buildSections($overviewRows, $phpVersion, $sapi, $os, $memoryLimit, $home);

        return new PhpInfoView(
            sections: $sections,
            tocEntries: $tocEntries,
            modulesHtml: $modulesHtml,
            configureCommand: self::pluck($overviewRows, 'Configure Command'),
        );
    }

    /**
     * @param array<string, string> $rows
     *
     * @return list<PhpInfoSection>
     */
    private static function buildSections(
        array $rows,
        string $phpVersion,
        string $sapi,
        string $os,
        string $memoryLimit,
        string $home,
    ): array {
        $sectionSpec = [
            [
                'eyebrow' => 'PHP version',
                'headline' => $phpVersion,
                'tiles' => [
                    'SAPI' => $sapi,
                    'OS' => $os,
                    'Server API' => self::pluck($rows, 'Server API'),
                    'Memory limit' => $memoryLimit,
                    'Virtual Dir Support' => self::pluck($rows, 'Virtual Directory Support'),
                ],
            ],
            [
                'eyebrow' => 'Build',
                'headline' => null,
                'tiles' => [
                    'Build Date' => self::pluck($rows, 'Build Date'),
                    'Build System' => self::pluck($rows, 'Build System'),
                    'PHP API' => self::pluck($rows, 'PHP API'),
                    'PHP Extension' => self::pluck($rows, 'PHP Extension'),
                    'Zend Extension' => self::pluck($rows, 'Zend Extension'),
                ],
            ],
            [
                'eyebrow' => 'Configuration',
                'headline' => null,
                'tiles' => [
                    'Loaded Configuration File' => self::pluck($rows, 'Loaded Configuration File'),
                    'Configuration File (php.ini) Path' => self::pluck($rows, 'Configuration File (php.ini) Path'),
                    'Scan this dir for additional .ini files' => self::pluck($rows, 'Scan this dir for additional .ini files'),
                    'Additional .ini files parsed' => self::pluck($rows, 'Additional .ini files parsed'),
                ],
            ],
            [
                'eyebrow' => 'Capabilities',
                'headline' => null,
                'tiles' => [
                    'Debug Build' => self::pluck($rows, 'Debug Build'),
                    'Thread Safety' => self::pluck($rows, 'Thread Safety'),
                    'Zend Signal Handling' => self::pluck($rows, 'Zend Signal Handling'),
                    'Zend Memory Manager' => self::pluck($rows, 'Zend Memory Manager'),
                    'IPv6 Support' => self::pluck($rows, 'IPv6 Support'),
                    'DTrace Support' => self::pluck($rows, 'DTrace Support'),
                ],
            ],
            [
                'eyebrow' => 'Streams',
                'headline' => null,
                'tiles' => [
                    'Registered PHP Streams' => self::pluck($rows, 'Registered PHP Streams'),
                    'Registered Stream Socket Transports' => self::pluck($rows, 'Registered Stream Socket Transports'),
                    'Registered Stream Filters' => self::pluck($rows, 'Registered Stream Filters'),
                ],
            ],
        ];

        $sections = [];

        foreach ($sectionSpec as $spec) {
            $tiles = self::buildTiles($spec['tiles'], $home);

            if ($tiles === [] && $spec['headline'] === null) {
                continue;
            }

            $sections[] = new PhpInfoSection(
                eyebrow: $spec['eyebrow'],
                tiles: $tiles,
                headline: $spec['headline'],
            );
        }

        return $sections;
    }

    private static function buildTile(string $label, string $value, string $home): PhpInfoTile
    {
        $lower = strtolower($value);

        if (in_array($lower, ['enabled', 'yes'], true)) {
            return new PhpInfoTile(
                label: $label,
                displayValue: $value,
                rawValue: $value,
                kind: PhpInfoTile::KIND_PILL_SUCCESS,
            );
        }

        if (in_array($lower, ['disabled', 'no'], true)) {
            return new PhpInfoTile(
                label: $label,
                displayValue: $value,
                rawValue: $value,
                kind: PhpInfoTile::KIND_PILL_MUTED,
            );
        }

        $isPathList = str_contains($value, ',')
            && (str_starts_with($value, '/') || str_starts_with($value, 'C:'));

        if ($isPathList) {
            $tokens = self::splitTrimmed($value, ',');
            $tokenDtos = array_map(
                static fn(string $f): PhpInfoToken => new PhpInfoToken(label: basename($f), title: $f),
                $tokens,
            );

            return new PhpInfoTile(
                label: $label,
                displayValue: $value,
                rawValue: $value,
                kind: PhpInfoTile::KIND_PATH_LIST,
                tokens: $tokenDtos,
            );
        }

        if (str_contains($value, ',')) {
            $tokens = self::splitTrimmed($value, ',');
            $isTokenList = count($tokens) > 1;

            foreach ($tokens as $t) {
                if ($t === '' || preg_match('/\s/', $t) === 1 || mb_strlen($t) > 32) {
                    $isTokenList = false;
                    break;
                }
            }

            if ($isTokenList) {
                $tokenDtos = array_map(
                    static fn(string $t): PhpInfoToken => new PhpInfoToken(label: $t),
                    $tokens,
                );

                return new PhpInfoTile(
                    label: $label,
                    displayValue: $value,
                    rawValue: $value,
                    kind: PhpInfoTile::KIND_TOKEN_LIST,
                    tokens: $tokenDtos,
                );
            }
        }

        $isPath = str_starts_with($value, '/') || str_starts_with($value, 'C:');

        if ($isPath) {
            return new PhpInfoTile(
                label: $label,
                displayValue: self::shortenPath($value, $home),
                rawValue: $value,
                kind: PhpInfoTile::KIND_PATH,
            );
        }

        return new PhpInfoTile(label: $label, displayValue: $value, rawValue: $value, kind: PhpInfoTile::KIND_TEXT);
    }

    /**
     * @param array<string, string> $rawTiles
     *
     * @return list<PhpInfoTile>
     */
    private static function buildTiles(array $rawTiles, string $home): array
    {
        $tiles = [];

        foreach ($rawTiles as $label => $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $tiles[] = self::buildTile($label, $value, $home);
        }

        return $tiles;
    }

    /**
     * @return array<string, string>
     */
    private static function parseOverviewRows(string $overviewSrc): array
    {
        $rows = [];

        if (
            preg_match_all(
                '%<table[^>]*>(.*?)</table>%s',
                $overviewSrc,
                $tableMatches,
            ) === false) {
            return $rows;
        }

        foreach ($tableMatches[1] as $tableHtml) {
            if (
                preg_match_all(
                    '%<tr[^>]*>\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>\s*</tr>%s',
                    $tableHtml,
                    $rowMatches,
                    PREG_SET_ORDER
                ) === false) {
                continue;
            }

            foreach ($rowMatches as $row) {
                $key = trim(html_entity_decode(strip_tags($row[1]), ENT_QUOTES, 'UTF-8'));
                $value = trim(html_entity_decode(strip_tags($row[2]), ENT_QUOTES, 'UTF-8'));

                if ($key === '' || stripos($key, 'PHP Logo') !== false) {
                    continue;
                }

                $rows[$key] = $value;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $rows
     */
    private static function pluck(array $rows, string $key): string
    {
        return $rows[$key] ?? '';
    }

    /**
     * Walks `$_SERVER` / `getenv()` / `posix_getpwuid()` looking for the active user's home directory; returns an
     * empty string when none of the signals are populated (Yii's built-in dev server is the typical case).
     */
    private static function resolveHomeDirectory(): string
    {
        foreach (['HOME', 'USERPROFILE'] as $envKey) {
            $candidate = $_SERVER[$envKey] ?? getenv($envKey);

            if (is_string($candidate) && $candidate !== '') {
                return rtrim($candidate, '/\\');
            }
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $info = @posix_getpwuid(@posix_getuid());

            if (is_array($info) && $info['dir'] !== '') {
                return rtrim($info['dir'], '/\\');
            }
        }

        return '';
    }

    private static function shortenPath(string $path, string $home): string
    {
        if ($home === '' || !str_starts_with($path, $home . '/')) {
            return $path;
        }

        return '~' . substr($path, strlen($home));
    }

    private static function slugify(string $title): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title));

        return 'phpinfo-' . trim($slug, '-');
    }

    /**
     * @return array{0: string, 1: string} `[overviewSrc, modulesSrc]`
     */
    private static function splitOverviewAndModules(string $body): array
    {
        $firstH2Pos = strpos($body, '<h2');

        if ($firstH2Pos === false) {
            return [$body, ''];
        }

        return [substr($body, 0, $firstH2Pos), substr($body, $firstH2Pos)];
    }

    /**
     * @return list<string>
     */
    private static function splitTrimmed(string $value, string $separator): array
    {
        if ($separator === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode($separator, $value)),
            static fn(string $entry): bool => $entry !== '',
        ));
    }

    /**
     * Wraps every `<h2>NAME</h2>` block in `<section class="...">` chrome and prepends every `<table>` with the
     * panel's `<div class="yii-debug-table-wrap">` wrap. Appends one {@see PhpInfoTocEntry} to `$tocEntries` per
     * captured `<h2>`.
     *
     * @param list<PhpInfoTocEntry> $tocEntries
     */
    private static function wrapModulesHtml(string $modulesSrc, array &$tocEntries): string
    {
        if ($modulesSrc === '') {
            return '';
        }

        $modulesSrc = str_replace(
            '<table',
            '<div class="yii-debug-table-wrap"><table class="yii-debug-table yii-debug-phpinfo__table" ',
            $modulesSrc,
        );
        $modulesSrc = str_replace('</table>', '</table></div>', $modulesSrc);

        $captured = [];

        $wrapped = preg_replace_callback(
            '%<h2[^>]*>(.*?)</h2>%s',
            static function (array $m) use (&$captured): string {
                $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                $slug = self::slugify($title);

                $captured[] = new PhpInfoTocEntry(title: $title, slug: $slug);

                return '</section><section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="'
                    . Encode::value($slug)
                    . '" data-section="' . Encode::value($title) . '">'
                    . '<header class="yii-debug-phpinfo-module-head">'
                    . '<span class="yii-debug-phpinfo-module-dot" aria-hidden="true"></span>'
                    . '<h2>' . Encode::content($title) . '</h2>'
                    . '</header>';
            },
            $modulesSrc,
        );

        $modulesSrc = $wrapped ?? $modulesSrc;

        $stripped = preg_replace('%^\s*</section>%', '', $modulesSrc);

        $modulesSrc = $stripped ?? $modulesSrc;

        foreach ($captured as $entry) {
            $tocEntries[] = $entry;
        }

        return $modulesSrc;
    }
}
