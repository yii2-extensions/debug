<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Form\{Button, InputSearch};
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Dd, Dl, Dt, Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Label, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Aside, Section};
use yii\debug\helpers\Icon;

use function array_keys;
use function count;
use function in_array;
use function strtolower;

/**
 * Renders the phpinfo page.
 *
 * Stateless static helpers: the public entry point takes a typed {@see PhpInfoView} and emits the shell (TOC sidebar
 * + main column with the search input, the Overview hero, the Configure Command details disclosure, and the modules
 * HTML). Per-section / per-tile rendering branches live in private helpers, so the view template collapses to a single
 * `render()` call.
 */
final class PhpInfoRenderer
{
    /**
     * Known phpinfo modules grouped by the job they perform. Unknown and third-party modules fall back to "Other".
     *
     * @var array<string, list<string>>
     */
    private const array TOC_GROUPS = [
        'Core & Runtime' => [
            'apcu',
            'core',
            'date',
            'ffi',
            'filter',
            'hash',
            'json',
            'pcre',
            'phar',
            'random',
            'reflection',
            'session',
            'spl',
            'standard',
            'xdebug',
            'zend opcache',
        ],
        'Database' => [
            'mongodb',
            'mysqli',
            'mysqlnd',
            'oci8',
            'odbc',
            'pdo',
            'pdo_dblib',
            'pdo_mysql',
            'pdo_oci',
            'pdo_odbc',
            'pdo_pgsql',
            'pdo_sqlite',
            'pgsql',
            'redis',
            'sqlite3',
        ],
        'Text & Localization' => [
            'ctype',
            'gettext',
            'iconv',
            'intl',
            'mbstring',
            'tokenizer',
        ],
        'Network & Security' => [
            'curl',
            'ftp',
            'openssl',
            'sockets',
            'sodium',
            'ssh2',
        ],
        'XML, Data & Media' => [
            'dom',
            'exif',
            'fileinfo',
            'gd',
            'imagick',
            'libxml',
            'simplexml',
            'xml',
            'xmlreader',
            'xmlwriter',
            'xsl',
        ],
        'System & Compression' => [
            'bz2',
            'calendar',
            'pcntl',
            'posix',
            'readline',
            'shmop',
            'sysvmsg',
            'sysvsem',
            'sysvshm',
            'zip',
            'zlib',
        ],
        'Environment' => [
            'additional modules',
            'apache2handler',
            'cgi-fcgi',
            'environment',
            'php credits',
            'php license',
            'php variables',
        ],
    ];

    /**
     * Renders the full phpinfo page (TOC sidebar + main column).
     */
    public static function render(PhpInfoView $view): string
    {
        return Div::tag()
            ->class('yii-debug-phpinfo-shell')
            ->html(
                self::renderToc($view->tocEntries),
                self::renderMain($view),
            )
            ->render();
    }

    /**
     * Renders the Configure Command details disclosure; empty string when the command is empty.
     */
    private static function renderConfigureCommand(string $command): string
    {
        if ($command === '') {
            return '';
        }

        return Details::tag()
            ->class('yii-debug-phpinfo-overview-details')
            ->html(
                Summary::tag()
                    ->html(
                        Span::tag()
                            ->class('yii-debug-phpinfo-overview-details-label')
                            ->content('Configure Command'),
                        Span::tag()
                            ->class('yii-debug-phpinfo-overview-details-hint')
                            ->content('click to expand'),
                    ),
                Pre::tag()
                    ->class('yii-debug-phpinfo-overview-details-body')
                    ->content($command),
            )
            ->render();
    }

    /**
     * Renders the main column (search input + Overview section + Configure Command details + raw modules HTML).
     */
    private static function renderMain(PhpInfoView $view): Div
    {
        $heroChildren = [];

        foreach ($view->sections as $section) {
            $heroChildren[] = self::renderSection($section);
        }

        $overviewBody = Div::tag()
            ->class('yii-debug-phpinfo-overview-hero')
            ->html(...$heroChildren);

        $overviewSection = Section::tag()
            ->addDataAttribute('section', 'Overview')
            ->class('yii-debug-phpinfo-section')
            ->id('phpinfo-overview')
            ->html(
                $overviewBody,
                self::renderConfigureCommand($view->configureCommand),
            );

        return Div::tag()
            ->class('yii-debug-phpinfo-main')
            ->html(
                self::renderSearch(),
                Div::tag()
                    ->id('yii-debug-phpinfo-results')
                    ->class('yii-debug-phpinfo-results')
                    ->html($overviewSection, $view->modulesHtml),
            );
    }

    /**
     * Renders the search field used by the filter JS (`data-yii-debug-phpinfo-search`).
     */
    private static function renderSearch(): Div
    {
        return Div::tag()
            ->class('yii-debug-phpinfo-search')
            ->addAttribute('role', 'search')
            ->addAriaAttribute('label', 'PHP configuration')
            ->html(
                Div::tag()
                    ->class('yii-debug-phpinfo-search-control')
                    ->html(
                        Label::tag()
                            ->class('yii-debug-sr-only')
                            ->for('yii-debug-phpinfo-filter')
                            ->content('Filter PHP modules and settings'),
                        Span::tag()
                            ->addAttribute('aria-hidden', 'true')
                            ->class('yii-debug-phpinfo-search-icon')
                            ->html(Icon::render('search')),
                        InputSearch::tag()
                            ->id('yii-debug-phpinfo-filter')
                            ->name('phpinfo-filter')
                            ->addAttribute('autocomplete', 'off')
                            ->addAttribute('spellcheck', 'false')
                            ->addAriaAttribute('controls', 'yii-debug-phpinfo-results')
                            ->addDataAttribute('yii-debug-phpinfo-search', true)
                            ->class('yii-debug-phpinfo-search-input')
                            ->placeholder('Search modules or settings…'),
                        Button::tag()
                            ->addAttribute('hidden', true)
                            ->addAriaAttribute('label', 'Clear PHP configuration search')
                            ->addDataAttribute('yii-debug-phpinfo-clear', true)
                            ->class('yii-debug-phpinfo-search-clear')
                            ->content('Clear')
                            ->type('button'),
                    ),
                Div::tag()
                    ->class('yii-debug-phpinfo-search-feedback')
                    ->html(
                        Span::tag()
                            ->addAriaAttribute('live', 'polite')
                            ->addDataAttribute('yii-debug-phpinfo-status', true)
                            ->class('yii-debug-phpinfo-search-status'),
                        Span::tag()
                            ->addAttribute('hidden', true)
                            ->addDataAttribute('yii-debug-phpinfo-empty', true)
                            ->class('yii-debug-phpinfo-search-empty')
                            ->content('No modules or settings match this query.'),
                    ),
            );
    }

    /**
     * Renders one Overview section (`<section>` with eyebrow header + optional hero headline + tile grid `<dl>`).
     */
    private static function renderSection(PhpInfoSection $section): Section
    {
        $blocks = [
            Header::tag()
                ->class('yii-debug-phpinfo-overview-block-head')
                ->html(
                    Span::tag()
                        ->class('yii-debug-phpinfo-overview-block-eyebrow')
                        ->content($section->eyebrow),
                ),
        ];

        if ($section->headline !== null) {
            $blocks[] = Div::tag()
                ->class('yii-debug-phpinfo-overview-hero-headline')
                ->html(
                    Strong::tag()
                        ->class('yii-debug-phpinfo-overview-hero-version')
                        ->content($section->headline),
                    Span::tag()
                        ->addAriaAttribute('hidden', 'true')
                        ->class('yii-debug-phpinfo-overview-hero-mark')
                        ->content('php'),
                );
        }

        $rows = [];

        foreach ($section->tiles as $tile) {
            $rows[] = self::renderTile($tile);
        }

        $blocks[] = Dl::tag()
            ->class('yii-debug-phpinfo-overview-hero-metrics')
            ->html(...$rows);

        return Section::tag()
            ->addAriaAttribute('label', $section->eyebrow)
            ->class('yii-debug-phpinfo-overview-hero-section')
            ->html(...$blocks);
    }

    /**
     * Renders one tile (`<div class="yii-debug-phpinfo-overview-hero-metric"><dt>label</dt><dd>value</dd></div>`),
     * branching on `$tile->kind` for the value layout.
     */
    private static function renderTile(PhpInfoTile $tile): Div
    {
        return Div::tag()
            ->class('yii-debug-phpinfo-overview-hero-metric')
            ->html(
                Dt::tag()->content($tile->label),
                Dd::tag()->html(self::renderTileValue($tile)),
            );
    }

    /**
     * Renders the inner `<dd>` content for one tile.
     */
    private static function renderTileValue(PhpInfoTile $tile): string
    {
        if ($tile->kind === PhpInfoTile::KIND_PILL_SUCCESS) {
            return Span::tag()
                ->addDataAttribute('variant', 'success')
                ->class('yii-debug-phpinfo-overview-pill')
                ->content($tile->displayValue)
                ->render();
        }

        if ($tile->kind === PhpInfoTile::KIND_PILL_MUTED) {
            return Span::tag()
                ->addDataAttribute('variant', 'muted')
                ->class('yii-debug-phpinfo-overview-pill')
                ->content($tile->displayValue)
                ->render();
        }

        if ($tile->kind === PhpInfoTile::KIND_PATH_LIST || $tile->kind === PhpInfoTile::KIND_TOKEN_LIST) {
            $codes = [];

            foreach ($tile->tokens as $token) {
                $code = Code::tag()
                    ->class('yii-debug-phpinfo-overview-token')
                    ->content($token->label);

                if ($token->title !== '') {
                    $code = $code->title($token->title);
                }

                $codes[] = $code;
            }

            return Span::tag()
                ->class('yii-debug-phpinfo-overview-files')
                ->html(...$codes)
                ->render();
        }

        if ($tile->kind === PhpInfoTile::KIND_PATH) {
            return Code::tag()
                ->content($tile->displayValue)
                ->title($tile->rawValue)
                ->render();
        }

        return Code::tag()
            ->content($tile->displayValue)
            ->render();
    }

    /**
     * Renders the TOC sidebar (`<aside>`) with one `<a>` per captured entry.
     *
     * @param list<PhpInfoTocEntry> $entries
     */
    private static function renderToc(array $entries): Aside
    {
        $groupedEntries = [];

        foreach (array_keys(self::TOC_GROUPS) as $label) {
            $groupedEntries[$label] = [];
        }

        $groupedEntries['Other'] = [];
        $moduleCount = 0;
        $overviewItem = null;

        foreach ($entries as $entry) {
            if ($entry->slug === 'phpinfo-overview') {
                $overviewItem = Li::tag()->html(self::renderTocLink($entry, true));

                continue;
            }

            $groupedEntries[self::resolveTocGroup($entry->title)][] = $entry;
            $moduleCount++;
        }

        $groups = [];

        foreach ($groupedEntries as $label => $groupEntries) {
            if ($groupEntries === []) {
                continue;
            }

            $items = [];

            foreach ($groupEntries as $entry) {
                $items[] = Li::tag()->html(self::renderTocLink($entry));
            }

            $groups[] = Details::tag()
                ->addDataAttribute('yii-debug-phpinfo-toc-group', true)
                ->class('yii-debug-phpinfo-toc-group')
                ->html(
                    Summary::tag()
                        ->class('yii-debug-phpinfo-toc-group-summary')
                        ->html(
                            Span::tag()->content($label),
                            Span::tag()
                                ->addAriaAttribute('label', count($groupEntries) . ' modules')
                                ->class('yii-debug-phpinfo-toc-group-count')
                                ->content((string) count($groupEntries)),
                        ),
                    Ul::tag()->class('yii-debug-phpinfo-toc-group-list')->html(...$items),
                );
        }

        $navigation = [];

        if ($overviewItem !== null) {
            $navigation[] = Ul::tag()
                ->class('yii-debug-phpinfo-toc-overview')
                ->html($overviewItem);
        }

        $navigation[] = Div::tag()
            ->class('yii-debug-phpinfo-toc-groups')
            ->html(...$groups);

        return Aside::tag()
            ->addAriaAttribute('label', 'phpinfo modules')
            ->class('yii-debug-phpinfo-toc')
            ->html(
                Header::tag()
                    ->class('yii-debug-phpinfo-toc-title')
                    ->html(
                        Span::tag()->content((string) $moduleCount),
                        Span::tag()->content('modules'),
                    ),
                ...$navigation,
            );
    }

    private static function renderTocLink(PhpInfoTocEntry $entry, bool $active = false): A
    {
        $link = A::tag()
            ->addDataAttribute('toc-target', $entry->slug)
            ->class('yii-debug-phpinfo-toc-link' . ($active ? ' is-active' : ''))
            ->content($entry->title)
            ->href("#{$entry->slug}");

        return $active ? $link->addAriaAttribute('current', 'page') : $link;
    }

    private static function resolveTocGroup(string $title): string
    {
        $normalizedTitle = strtolower($title);

        foreach (self::TOC_GROUPS as $label => $modules) {
            if (in_array($normalizedTitle, $modules, true)) {
                return $label;
            }
        }

        return 'Other';
    }
}
