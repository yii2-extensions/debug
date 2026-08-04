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
use function getenv;
use function html_entity_decode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function posix_getpwuid;
use function posix_getuid;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function sprintf;
use function str_contains;
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
 * module heading + table that follows), parses each `<tr><td>k</td><td>v</td></tr>` pair into a key/value map, builds
 * the five hero sections ('PHP version' / 'Build' / 'Configuration' / 'Capabilities' / 'Streams') with typed tiles,
 * and wraps every module `<table>` in the panel's table chrome plus a deep-link `<section>` so the search filter and
 * the TOC can hide / jump to it.
 */
final class PhpInfoDataNormalizer
{
    /**
     * Matches a `class` attribute together with its leading whitespace, in either quoting style.
     */
    private const string CLASS_ATTRIBUTE_PATTERN = '/\s*\bclass=("[^"]*"|\'[^\']*\')/i';
    /**
     * Matches the key cell of a phpinfo row (`class="e"`), whether it is still a `<td>` or already a row header.
     */
    private const string KEY_CELL_PATTERN = '%<(?:th|td)\b[^>]*class="[^"]*\be\b[^"]*"[^>]*>(.*?)</(?:th|td)>%si';
    /**
     * Matches phpinfo's header row (`<tr class="h">`) anchored at the start of a table body.
     */
    private const string LEADING_HEADER_ROW_PATTERN = '%^\s*<tr\b[^>]*class="[^"]*\bh\b[^"]*"[^>]*>.*?</tr>%si';
    /**
     * phpinfo() exposes environment variables and superglobals verbatim. Keep credentials out of the rendered HTML
     * while leaving ordinary module directives (for example, session.cookie_path) untouched.
     */
    private const string SENSITIVE_VARIABLE_PATTERN
        = '/password|passwd|token|secret|credential|authorization|signature|php[_-]?auth|auth[_-]?key|cookie|session[_-]?(?:id|key|token)|xsrf|csrf|(?:api|app|private|access|encryption|signing)[_-]?key|(?:database|db)[_-]?url|dsn/i';
    /**
     * Values reported by extensions for a capability that is unavailable.
     */
    private const array STATUS_MUTED = ['disabled', 'inactive', 'no', 'not compiled in', 'not supported', 'off'];
    /**
     * Values reported by extensions for a capability that is available.
     */
    private const array STATUS_SUCCESS = ['active', 'enabled', 'on', 'supported', 'up and running', 'yes'];
    /**
     * Matches a `<th>` or `<td>` and captures its content.
     */
    private const string TABLE_CELL_PATTERN = '%<(?:th|td)\b[^>]*>(.*?)</(?:th|td)>%si';
    /**
     * Free-form key/value table ('Variable | Value', statistics, contributor lists).
     */
    private const string TABLE_KIND_DATA = 'data';
    /**
     * `php.ini` directive table with local and master values.
     */
    private const string TABLE_KIND_DIRECTIVES = 'directives';
    /**
     * Module information table: capability flags and versions.
     */
    private const string TABLE_KIND_FACTS = 'facts';
    /**
     * Single-column prose table (licenses, credits blurbs).
     */
    private const string TABLE_KIND_NOTE = 'note';
    /**
     * Matches a `<table>` and captures its body.
     */
    private const string TABLE_PATTERN = '%<table\b[^>]*>(.*?)</table>%si';
    /**
     * Matches a `<tr>` and captures its attributes and its content.
     */
    private const string TABLE_ROW_PATTERN = '%<tr\b([^>]*)>(.*?)</tr>%si';

    /**
     * Builds the typed view-model from the raw {@see phpinfo()} HTML body, the runtime metrics PHP itself reports
     * (SAPI, OS, memory limit) and the active {@see PHP_VERSION} constant.
     *
     * @param string $body Captured `phpinfo()` output, already narrowed to its `<body>` content.
     * @param string $phpVersion Running PHP version used as the hero headline.
     * @param string $sapi Server API name.
     * @param string $os Host operating system description.
     * @param string $memoryLimit Effective `memory_limit` directive.
     */
    public static function fromOutput(
        string $body,
        string $phpVersion,
        string $sapi,
        string $os,
        string $memoryLimit,
    ): PhpInfoView {
        $home = self::resolveHomeDirectory();

        [$overviewSrc, $modulesSrc] = self::splitOverviewAndModules($body);

        $overviewRows = self::parseOverviewRows($overviewSrc);

        $tocEntries = [new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview')];
        $compactModules = [];

        $modulesHtml = self::wrapModulesHtml($modulesSrc, $tocEntries, $compactModules, $home);
        $sections = self::buildSections($overviewRows, $phpVersion, $sapi, $os, $memoryLimit, $home);

        return new PhpInfoView(
            sections: $sections,
            tocEntries: $tocEntries,
            compactModules: $compactModules,
            modulesHtml: $modulesHtml,
            configureCommand: self::shortenHomePaths(self::pluck($overviewRows, 'Configure Command'), $home),
        );
    }

    /**
     * Replaces the class of a `<tr>` attribute string, preserving any other attribute.
     *
     * Rows reaching this point are restyled wholesale, so phpinfo's own presentational markers (`h`, `v`) are dropped
     * rather than merged.
     *
     * @param string $attributes Raw attribute string captured from the source row.
     * @param string $class Class to apply.
     */
    private static function addRowClass(string $attributes, string $class): string
    {
        $withoutClass = (string) preg_replace(self::CLASS_ATTRIBUTE_PATTERN, '', trim($attributes));

        return trim("{$withoutClass} class=\"{$class}\"");
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
                    'Server API' => self::pluck(
                        $rows,
                        'Server API',
                    ),
                    'Memory limit' => $memoryLimit,
                    'Virtual Dir Support' => self::pluck(
                        $rows,
                        'Virtual Directory Support',
                    ),
                ],
            ],
            [
                'eyebrow' => 'Build',
                'headline' => null,
                'tiles' => [
                    'Build Date' => self::pluck(
                        $rows,
                        'Build Date',
                    ),
                    'Build System' => self::pluck(
                        $rows,
                        'Build System',
                    ),
                    'PHP API' => self::pluck(
                        $rows,
                        'PHP API',
                    ),
                    'PHP Extension' => self::pluck(
                        $rows,
                        'PHP Extension',
                    ),
                    'Zend Extension' => self::pluck(
                        $rows,
                        'Zend Extension',
                    ),
                ],
            ],
            [
                'eyebrow' => 'Configuration',
                'headline' => null,
                'tiles' => [
                    'Loaded Configuration File' => self::pluck(
                        $rows,
                        'Loaded Configuration File',
                    ),
                    'Configuration File (php.ini) Path' => self::pluck(
                        $rows,
                        'Configuration File (php.ini) Path',
                    ),
                    'Scan this dir for additional .ini files' => self::pluck(
                        $rows,
                        'Scan this dir for additional .ini files',
                    ),
                    'Additional .ini files parsed' => self::pluck(
                        $rows,
                        'Additional .ini files parsed',
                    ),
                ],
            ],
            [
                'eyebrow' => 'Capabilities',
                'headline' => null,
                'tiles' => [
                    'Debug Build' => self::pluck(
                        $rows,
                        'Debug Build',
                    ),
                    'Thread Safety' => self::pluck(
                        $rows,
                        'Thread Safety',
                    ),
                    'Zend Signal Handling' => self::pluck(
                        $rows,
                        'Zend Signal Handling',
                    ),
                    'Zend Memory Manager' => self::pluck(
                        $rows,
                        'Zend Memory Manager',
                    ),
                    'IPv6 Support' => self::pluck(
                        $rows,
                        'IPv6 Support',
                    ),
                    'DTrace Support' => self::pluck(
                        $rows,
                        'DTrace Support',
                    ),
                ],
            ],
            [
                'eyebrow' => 'Streams',
                'headline' => null,
                'tiles' => [
                    'Registered PHP Streams' => self::pluck(
                        $rows,
                        'Registered PHP Streams',
                    ),
                    'Registered Stream Socket Transports' => self::pluck(
                        $rows,
                        'Registered Stream Socket Transports',
                    ),
                    'Registered Stream Filters' => self::pluck(
                        $rows,
                        'Registered Stream Filters',
                    ),
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

    /**
     * Classifies one label/value pair into the tile kind that drives its rendering branch.
     *
     * @param string $label Row key reported by {@see phpinfo()}.
     * @param string $value Row value reported by {@see phpinfo()}.
     * @param string $home Home directory used to shorten absolute paths.
     */
    private static function buildTile(string $label, string $value, string $home): PhpInfoTile
    {
        $variant = self::resolveStatusVariant($value);

        if ($variant !== null) {
            return new PhpInfoTile(
                label: $label,
                displayValue: $value,
                rawValue: $value,
                kind: $variant,
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
     * Builds one tile per non-empty value, preserving the input order.
     *
     * @param array<string, string> $rawTiles Label/value pairs plucked from the overview rows.
     * @param string $home Home directory used to shorten absolute paths.
     *
     * @return list<PhpInfoTile> Tiles for the values that carry content.
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
     * Returns the `TABLE_KIND_*` describing the table, inferred from its header row and its cell density.
     *
     * @param string $tableBody Inner HTML of the table.
     */
    private static function classifyModuleTable(string $tableBody): string
    {
        $headerCells = self::extractFirstHeaderCells($tableBody);
        $firstHeader = strtolower($headerCells[0] ?? '');
        $secondHeader = strtolower($headerCells[1] ?? '');

        if ($firstHeader === 'directive') {
            return self::TABLE_KIND_DIRECTIVES;
        }

        if (
            in_array($firstHeader, ['contribution', 'module', 'module name', 'statistics', 'variable'], true)
            || $secondHeader === 'value'
            || $secondHeader === 'authors'
        ) {
            return self::TABLE_KIND_DATA;
        }

        if (self::countRowsWithValues($tableBody, false) === 0) {
            return self::TABLE_KIND_NOTE;
        }

        return self::TABLE_KIND_FACTS;
    }

    /**
     * Counts the rows carrying at least two cells, that is, the rows that hold a value.
     *
     * @param string $tableBody Inner HTML of the table.
     * @param bool $skipHeaderRows Whether phpinfo's own `class="h"` rows are excluded from the count.
     */
    private static function countRowsWithValues(string $tableBody, bool $skipHeaderRows): int
    {
        preg_match_all(self::TABLE_ROW_PATTERN, $tableBody, $rows, PREG_SET_ORDER);

        $count = 0;

        foreach ($rows as $row) {
            if ($skipHeaderRows && preg_match('/\bclass="[^"]*\bh\b[^"]*"/i', $row[1]) === 1) {
                continue;
            }

            preg_match_all('%<(?:th|td)\b[^>]*>%i', $row[2], $cells);

            if (count($cells[0]) >= 2) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Counts every row, regardless of how many cells it carries.
     *
     * @param string $tableBody Inner HTML of the table.
     */
    private static function countTableRows(string $tableBody): int
    {
        preg_match_all('%<tr\b[^>]*>%i', $tableBody, $rows);

        return count($rows[0]);
    }

    /**
     * Decodes a captured HTML cell into its plain-text value.
     *
     * @param string $html Cell content as captured from the source markup.
     */
    private static function decodeCell(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Returns a compact Overview module when the block contains exactly one facts table with one or two values.
     *
     * Reporting blocks such as PHP Variables or PHP Credits never qualify: they are not extensions, so they keep their
     * own panel even when they report a single value. Modules with directives, notes, statistics, multiple tables, or
     * long value lists also retain their own panel.
     *
     * @param string $title Module title reported by {@see phpinfo()}.
     * @param string $slug Deep-link slug retained by the Overview summary.
     * @param string $moduleBody Module HTML between its heading and the next one.
     * @param string $home Home directory used to shorten absolute paths.
     */
    private static function extractCompactModule(
        string $title,
        string $slug,
        string $moduleBody,
        string $home,
    ): PhpInfoCompactModule|null {
        if (PhpInfoModuleGroup::isExtension($title) === false) {
            return null;
        }

        preg_match_all(self::TABLE_PATTERN, $moduleBody, $tables, PREG_SET_ORDER);

        if (count($tables) !== 1 || self::classifyModuleTable($tables[0][1]) !== self::TABLE_KIND_FACTS) {
            return null;
        }

        preg_match_all('%<tr\b[^>]*>(.*?)</tr>%si', $tables[0][1], $rows, PREG_SET_ORDER);

        $tiles = [];

        foreach ($rows as $row) {
            preg_match_all(self::TABLE_CELL_PATTERN, $row[1], $cells, PREG_SET_ORDER);

            if (count($cells) < 2) {
                continue;
            }

            $label = self::decodeCell($cells[0][1]);
            $value = self::decodeCell($cells[1][1]);

            if ($label === '' || $value === '') {
                return null;
            }

            if (str_contains($value, ',') && count(self::splitTrimmed($value, ',')) > 3) {
                return null;
            }

            $tiles[] = self::buildTile($label, $value, $home);
        }

        if ($tiles === [] || count($tiles) > 2) {
            return null;
        }

        return new PhpInfoCompactModule(title: $title, slug: $slug, tiles: $tiles);
    }

    /**
     * Decodes the cells of phpinfo's header row; empty list when the table carries no header row.
     *
     * @param string $tableBody Inner HTML of the table.
     *
     * @return list<string> Decoded header cells in source order.
     */
    private static function extractFirstHeaderCells(string $tableBody): array
    {
        if (preg_match('%<tr\b[^>]*class="[^"]*\bh\b[^"]*"[^>]*>(.*?)</tr>%si', $tableBody, $row) !== 1) {
            return [];
        }

        preg_match_all(self::TABLE_CELL_PATTERN, $row[1], $cells, PREG_SET_ORDER);

        return array_map(static fn(array $cell): string => self::decodeCell($cell[1]), $cells);
    }

    /**
     * Decodes the key cell of a row; `null` when the row carries no key cell.
     *
     * @param string $rowContent Inner HTML of the row.
     */
    private static function extractKeyCell(string $rowContent): string|null
    {
        if (preg_match(self::KEY_CELL_PATTERN, $rowContent, $keyCell) !== 1) {
            return null;
        }

        return self::decodeCell($keyCell[1]);
    }

    /**
     * Returns the table caption phpinfo emits as a single-cell header row; empty string when the row spans two cells.
     *
     * @param string $tableBody Inner HTML of the table.
     */
    private static function extractTableTitle(string $tableBody): string
    {
        $headerCells = self::extractFirstHeaderCells($tableBody);

        return count($headerCells) === 1 ? $headerCells[0] : '';
    }

    /**
     * Returns the count badge text for a table ('12 directives', '1 row').
     *
     * @param int $count Number of counted rows.
     * @param string $kind One of the `TABLE_KIND_*` constants.
     */
    private static function formatModuleTableCount(int $count, string $kind): string
    {
        $noun = match ($kind) {
            self::TABLE_KIND_DIRECTIVES => $count === 1 ? 'directive' : 'directives',
            self::TABLE_KIND_DATA => $count === 1 ? 'row' : 'rows',
            self::TABLE_KIND_NOTE => $count === 1 ? 'note' : 'notes',
            default => $count === 1 ? 'value' : 'values',
        };

        return "$count $noun";
    }

    /**
     * Whether the variable name looks like a credential that must not reach the rendered HTML.
     *
     * @param string $key Variable name as reported by {@see phpinfo()}.
     */
    private static function isSensitiveVariableKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_VARIABLE_PATTERN, $key) === 1;
    }

    /**
     * Whether the module carries anything beyond its heading and its table captions.
     *
     * @param string $moduleBody Module HTML between its heading and the next one.
     */
    private static function moduleBodyHasContent(string $moduleBody): bool
    {
        preg_match_all(self::TABLE_PATTERN, $moduleBody, $tables, PREG_SET_ORDER);

        foreach ($tables as $table) {
            $tableBody = $table[1];

            if (self::extractTableTitle($tableBody) !== '') {
                $tableBody = self::stripHeaderRow($tableBody);
            }

            if (trim(strip_tags($tableBody)) !== '') {
                return true;
            }
        }

        $withoutTables = preg_replace('%<table\b[^>]*>.*?</table>%si', '', $moduleBody);

        return trim(strip_tags($withoutTables ?? $moduleBody)) !== '';
    }

    /**
     * Returns the human label shown in the table's head bar.
     *
     * @param string $kind One of the `TABLE_KIND_*` constants.
     * @param string $tableBody Inner HTML of the table, used to refine the label of data tables.
     */
    private static function moduleTableLabel(string $kind, string $tableBody): string
    {
        if ($kind === self::TABLE_KIND_DIRECTIVES) {
            return 'Configuration directives';
        }

        if ($kind === self::TABLE_KIND_FACTS) {
            return 'Module information';
        }

        if ($kind === self::TABLE_KIND_NOTE) {
            return 'Notes';
        }

        $firstHeader = strtolower(self::extractFirstHeaderCells($tableBody)[0] ?? '');

        return match ($firstHeader) {
            'contribution' => 'Contributors',
            'module', 'module name' => 'Modules',
            'statistics' => 'Statistics',
            'variable' => 'Variables',
            default => 'Data',
        };
    }

    /**
     * Tags the rows of a facts table: single-cell rows become full-width subheadings, the rest get the fact classes
     * and their on/off values become status pills.
     *
     * @param string $tableBody Inner HTML of the table.
     */
    private static function normalizeFactRows(string $tableBody): string
    {
        $normalized = preg_replace_callback(
            self::TABLE_ROW_PATTERN,
            static function (array $row): string {
                preg_match_all(self::TABLE_CELL_PATTERN, $row[2], $cells, PREG_SET_ORDER);

                if (count($cells) === 1) {
                    $content = trim($cells[0][1]);
                    $attributes = self::addRowClass($row[1], 'yii-debug-phpinfo-fact-subheading');

                    return sprintf('<tr %s><th colspan="2">%s</th></tr>', $attributes, $content);
                }

                $value = isset($cells[1]) ? self::decodeCell($cells[1][1]) : '';
                $class = mb_strlen($value) > 72
                    ? 'yii-debug-phpinfo-fact yii-debug-phpinfo-fact-wide'
                    : 'yii-debug-phpinfo-fact';
                $attributes = self::addRowClass($row[1], $class);

                return '<tr ' . $attributes . '>' . self::renderFactStatusPills($row[2]) . '</tr>';
            },
            $tableBody,
        );

        return $normalized ?? $tableBody;
    }

    /**
     * Wraps one module table in the panel's table chrome: head bar with the label and the row-count badge, plus a
     * horizontal scroll container.
     *
     * @param string $tableBody Inner HTML of the table.
     * @param string $labelOverride Label replacing the inferred one; empty string to keep the inferred label.
     * @param bool $collapsible Whether the chrome is a `<details>` disclosure instead of a plain `<div>`.
     * @param bool $open Whether the disclosure starts expanded; ignored when `$collapsible` is `false`.
     */
    private static function normalizeModuleTable(
        string $tableBody,
        string $labelOverride = '',
        bool $collapsible = false,
        bool $open = false,
    ): string {
        $tableTitle = self::extractTableTitle($tableBody);

        if ($tableTitle !== '') {
            $tableBody = self::stripHeaderRow($tableBody);
        }

        $kind = self::classifyModuleTable($tableBody);
        $label = $labelOverride !== ''
            ? $labelOverride
            : ($tableTitle !== '' ? $tableTitle : self::moduleTableLabel($kind, $tableBody));

        if ($kind === self::TABLE_KIND_FACTS) {
            $tableBody = self::normalizeFactRows($tableBody);
        }

        $skipHeaders = $kind === self::TABLE_KIND_DIRECTIVES || $kind === self::TABLE_KIND_DATA;
        $count = $kind === self::TABLE_KIND_NOTE
            ? self::countTableRows($tableBody)
            : self::countRowsWithValues($tableBody, $skipHeaders);
        $countLabel = self::formatModuleTableCount($count, $kind);
        $encodedLabel = Encode::content($label);
        $headTag = $collapsible ? 'summary' : 'header';
        $containerTag = $collapsible ? 'details' : 'div';
        $attributes = $collapsible
            ? sprintf(
                ' data-yii-debug-phpinfo-collapsible="true" data-yii-debug-phpinfo-default-open="%s"%s',
                $open ? 'true' : 'false',
                $open ? ' open' : '',
            )
            : '';

        return sprintf(
            '<%s class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-%s%s"%s><%s class="yii-debug-phpinfo-table-section-head"><span>%s</span><span class="yii-debug-phpinfo-table-section-count">%s</span></%s><div class="yii-debug-phpinfo-table-scroll"><table aria-label="%s" class="yii-debug-table is-%s">%s</table></div></%s>',
            $containerTag,
            $kind,
            $collapsible ? ' yii-debug-phpinfo-variable-group' : '',
            $attributes,
            $headTag,
            $encodedLabel,
            Encode::content($countLabel),
            $headTag,
            Encode::value($label),
            $kind,
            $tableBody,
            $containerTag,
        );
    }

    /**
     * Splits a 'Variable | Value' table into per-superglobal collapsible groups (Request, Cookies, Server,
     * Environment, Other).
     *
     * @param string $tableBody Inner HTML of the table.
     *
     * @return string|null Grouped markup, or `null` when the table is not a variable table.
     */
    private static function normalizeVariableTables(string $tableBody): string|null
    {
        $headers = self::extractFirstHeaderCells($tableBody);

        if (strtolower($headers[0] ?? '') !== 'variable' || strtolower($headers[1] ?? '') !== 'value') {
            return null;
        }

        preg_match_all(self::TABLE_ROW_PATTERN, $tableBody, $rows, PREG_SET_ORDER);

        $header = '';
        $groups = [
            'Request' => [],
            'Cookies' => [],
            'Server' => [],
            'Environment' => [],
            'Other' => [],
        ];

        foreach ($rows as $row) {
            if (preg_match('/\bclass="[^"]*\bh\b[^"]*"/i', $row[1]) === 1) {
                $header = $row[0];

                continue;
            }

            $key = self::extractKeyCell($row[2]);
            $groups[$key === null ? 'Other' : self::resolveVariableGroup($key)][] = $row[0];
        }

        $rendered = [];
        $open = true;

        foreach ($groups as $label => $groupRows) {
            if ($groupRows === []) {
                continue;
            }

            $rendered[] = self::normalizeModuleTable(
                $header . implode('', $groupRows),
                $label,
                true,
                $open,
            );
            $open = false;
        }

        return $rendered === [] ? null : implode('', $rendered);
    }

    /**
     * @return array<string, string>
     */
    private static function parseOverviewRows(string $overviewSrc): array
    {
        $rows = [];

        preg_match_all(
            '%<tr[^>]*>\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>\s*</tr>%s',
            $overviewSrc,
            $rowMatches,
            PREG_SET_ORDER,
        );

        foreach ($rowMatches as $row) {
            $key = trim(html_entity_decode(strip_tags($row[1]), ENT_QUOTES, 'UTF-8'));
            $value = trim(html_entity_decode(strip_tags($row[2]), ENT_QUOTES, 'UTF-8'));

            if ($key === '' || stripos($key, 'PHP Logo') !== false) {
                continue;
            }

            $rows[$key] = $value;
        }

        return $rows;
    }

    /**
     * Returns the value of an overview row; empty string when the key is absent.
     *
     * @param array<string, string> $rows Overview key/value map.
     * @param string $key Row key to read.
     */
    private static function pluck(array $rows, string $key): string
    {
        return $rows[$key] ?? '';
    }

    /**
     * Replaces the value of every credential-looking row with an accessible placeholder.
     *
     * @param string $tableBody Inner HTML of the table.
     */
    private static function redactSensitiveRows(string $tableBody): string
    {
        $redacted = preg_replace_callback(
            self::TABLE_ROW_PATTERN,
            static function (array $row): string {
                $key = self::extractKeyCell($row[2]);

                if ($key === null || self::isSensitiveVariableKey($key) === false) {
                    return $row[0];
                }

                $content = preg_replace(
                    '%<td\b([^>]*)class="[^"]*\bv\b[^"]*"([^>]*)>.*?</td>%si',
                    '<td $1class="v"$2><span class="yii-debug-phpinfo-redacted" aria-label="Sensitive value hidden">redacted</span></td>',
                    $row[2],
                );

                return '<tr' . $row[1] . '>' . ($content ?? $row[2]) . '</tr>';
            },
            $tableBody,
        );

        return $redacted ?? $tableBody;
    }

    /**
     * Wraps the on/off values of a facts row in status pills, leaving every other value untouched.
     *
     * @param string $rowContent Inner HTML of the row.
     */
    private static function renderFactStatusPills(string $rowContent): string
    {
        $rendered = preg_replace_callback(
            '%<td\b([^>]*)>(.*?)</td>%si',
            static function (array $cell): string {
                if (preg_match('/\bclass="[^"]*\bv\b[^"]*"/i', $cell[1]) !== 1) {
                    return $cell[0];
                }

                $kind = self::resolveStatusVariant(self::decodeCell($cell[2]));

                if ($kind === null) {
                    return $cell[0];
                }

                $variant = $kind === PhpInfoTile::KIND_PILL_SUCCESS ? 'success' : 'muted';

                return sprintf(
                    '<td%s><span class="yii-debug-phpinfo-status-pill" data-variant="%s">%s</span></td>',
                    $cell[1],
                    $variant,
                    trim($cell[2]),
                );
            },
            $rowContent,
        );

        return $rendered ?? $rowContent;
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

        // `function_exists` stays unimported so `MockerExtension` can swap it for the posix-less coverage case.
        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $info = @posix_getpwuid(@posix_getuid());

            if (is_array($info) && $info['dir'] !== '') {
                return rtrim($info['dir'], '/\\');
            }
        }

        return '';
    }

    /**
     * Returns the pill kind matching an on/off value, or `null` when the value is not a status.
     *
     * @param string $value Value reported by {@see phpinfo()}.
     *
     * @return string|null {@see PhpInfoTile::KIND_PILL_SUCCESS}, {@see PhpInfoTile::KIND_PILL_MUTED}, or `null`.
     */
    private static function resolveStatusVariant(string $value): string|null
    {
        $normalized = strtolower($value);

        if (in_array($normalized, self::STATUS_SUCCESS, true)) {
            return PhpInfoTile::KIND_PILL_SUCCESS;
        }

        return in_array($normalized, self::STATUS_MUTED, true) ? PhpInfoTile::KIND_PILL_MUTED : null;
    }

    /**
     * Buckets a variable name by the superglobal it belongs to.
     *
     * @param string $key Variable name as reported by {@see phpinfo()}.
     */
    private static function resolveVariableGroup(string $key): string
    {
        foreach (['$_REQUEST[', '$_GET[', '$_POST[', '$_FILES['] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return 'Request';
            }
        }

        if (str_starts_with($key, '$_COOKIE[')) {
            return 'Cookies';
        }

        if (str_starts_with($key, '$_SERVER[')) {
            return 'Server';
        }

        if (str_starts_with($key, '$_ENV[') || ($key !== '' && strtolower($key) !== $key)) {
            return 'Environment';
        }

        return 'Other';
    }

    /**
     * Replaces every home directory occurrence inside free-form text with `~`, keeping the Configure Command in step
     * with the shortened Configuration paths instead of leaking the account name.
     *
     * The lookbehind anchors the match to a path boundary, so an unrelated directory ending in the home path (for
     * example `/opt/home/dev`) survives untouched.
     *
     * @param string $value Text that may embed absolute paths.
     * @param string $home Home directory; empty string leaves the text untouched.
     */
    private static function shortenHomePaths(string $value, string $home): string
    {
        if ($home === '') {
            return $value;
        }

        $pattern = '#(?<![\w/])' . preg_quote($home, '#') . '/#';

        return (string) preg_replace($pattern, '~/', $value);
    }

    /**
     * Replaces the home directory prefix of an absolute path with `~`.
     *
     * @param string $path Absolute path to shorten.
     * @param string $home Home directory; empty string leaves the path untouched.
     */
    private static function shortenPath(string $path, string $home): string
    {
        $homePath = "{$home}/";

        if ($home === '' || !str_starts_with($path, $homePath)) {
            return $path;
        }

        return '~' . substr($path, strlen($home));
    }

    /**
     * Returns the deep-link anchor id for a module title ('Zend OPcache' becomes `phpinfo-zend-opcache`).
     *
     * @param string $title Module title reported by {@see phpinfo()}.
     */
    private static function slugify(string $title): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title));

        return 'phpinfo-' . trim($slug, '-');
    }

    /**
     * Splits the captured body at the first module heading.
     *
     * @param string $body Captured `phpinfo()` output.
     *
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
     * Splits a delimited value, trimming every entry and dropping the empty ones.
     *
     * @param string $value Delimited value.
     * @param non-empty-string $separator Delimiter to split on.
     *
     * @return list<string> Non-empty trimmed entries in source order.
     */
    private static function splitTrimmed(string $value, string $separator): array
    {
        return array_values(
            array_filter(
                array_map(trim(...), explode($separator, $value)),
                static fn(string $entry): bool => $entry !== '',
            ),
        );
    }

    /**
     * Drops phpinfo's leading `<tr class="h">` caption row from a table body.
     *
     * @param string $tableBody Inner HTML of the table.
     */
    private static function stripHeaderRow(string $tableBody): string
    {
        return preg_replace(self::LEADING_HEADER_ROW_PATTERN, '', $tableBody, 1) ?? $tableBody;
    }

    /**
     * Normalizes every phpinfo module. Small facts-only modules move into `$compactModules`; modules with meaningful
     * detail keep their standalone section and TOC entry. PHP emits regular modules as `<h2>` and Credits as `<h1>`.
     *
     * @param string $modulesSrc Modules chunk of the captured output.
     * @param list<PhpInfoTocEntry> $tocEntries TOC entries, appended in place for every standalone module.
     * @param list<PhpInfoCompactModule> $compactModules Compact modules, appended in place for every summarized module.
     * @param string $home Home directory used to shorten absolute paths.
     */
    private static function wrapModulesHtml(
        string $modulesSrc,
        array &$tocEntries,
        array &$compactModules,
        string $home,
    ): string {
        preg_match_all(
            '%<h([12])[^>]*>(.*?)</h\1>(.*?)(?=<h[12]\b[^>]*>|$)%si',
            $modulesSrc,
            $modules,
            PREG_SET_ORDER,
        );

        $rendered = [];

        foreach ($modules as $module) {
            $title = self::decodeCell($module[2]);
            $slug = self::slugify($title);
            $body = $module[3];

            if (!self::moduleBodyHasContent($body)) {
                continue;
            }

            $compactModule = self::extractCompactModule($title, $slug, $body, $home);

            if ($compactModule !== null) {
                $compactModules[] = $compactModule;

                continue;
            }

            $rowHeaders = preg_replace(
                '%<td class="e">(.*?)</td>%s',
                '<th scope="row" class="e">$1</th>',
                $body,
            );
            $body = $rowHeaders ?? $body;
            $body = self::wrapModuleTables(
                $body,
                in_array(strtolower($title), ['environment', 'php variables'], true),
            );

            $tocEntries[] = new PhpInfoTocEntry(title: $title, slug: $slug);
            $encodedSlug = Encode::value($slug);
            $encodedTitle = Encode::value($title);
            $encodedContent = Encode::content($title);

            $rendered[] = sprintf(
                '<section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="%s" data-section="%s"><header class="yii-debug-phpinfo-module-head"><h2 id="%s-heading">%s</h2></header>%s</section>',
                $encodedSlug,
                $encodedTitle,
                $encodedSlug,
                $encodedContent,
                $body,
            );
        }

        return implode('', $rendered);
    }

    /**
     * Wraps every `<table>` of a module in the panel's table chrome, splitting variable tables into superglobal
     * groups when their shape allows it.
     *
     * @param string $modulesSrc Module HTML to rewrite.
     * @param bool $redactSensitiveVariables Whether credential-looking values are replaced before rendering.
     */
    private static function wrapModuleTables(string $modulesSrc, bool $redactSensitiveVariables): string
    {
        $wrapped = preg_replace_callback(
            self::TABLE_PATTERN,
            static function (array $table) use ($redactSensitiveVariables): string {
                $tableBody = $redactSensitiveVariables ? self::redactSensitiveRows($table[1]) : $table[1];

                return self::normalizeVariableTables($tableBody) ?? self::normalizeModuleTable($tableBody);
            },
            $modulesSrc,
        );

        return $wrapped ?? $modulesSrc;
    }
}
