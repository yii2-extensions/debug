<?php

declare(strict_types=1);

namespace yii\debug\widgets\phpinfo;

use PHPForge\Debug\Helper\{Disclosure, Icon};
use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Form\{Button, InputSearch};
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Dd, Dl, Dt, Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Label, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Aside, Section};

use function count;
use function implode;
use function in_array;
use function mb_strlen;

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
     * Renders the full phpinfo page (TOC sidebar + main column).
     *
     * @param PhpInfoView $view Normalized page model produced by {@see PhpInfoDataNormalizer::fromOutput()}.
     */
    public static function render(PhpInfoView $view): string
    {
        return Div::tag()
            ->class('yii-debug-phpinfo-shell')
            ->html(
                self::renderToc($view->tocEntries, count($view->compactModules)),
                self::renderMain($view),
            )
            ->render();
    }

    /**
     * Returns a pluralized module count ('1 module', '24 modules') matching the wording of the search JS.
     *
     * @param int $count Number of modules to announce.
     */
    private static function formatModuleCount(int $count): string
    {
        return $count . ($count === 1 ? ' module' : ' modules');
    }

    /**
     * Renders one summarized module as an extension pill (LED + name + version or on/off state).
     *
     * The pill shares the Config panel's `yii-debug-ext-pill` markup, so both extension strips stay identical. Facts
     * the compact slot cannot show land in the `title` attribute and in a screen-reader-only span, which also keeps
     * them reachable from the search filter.
     *
     * @param PhpInfoCompactModule $module Module summarized in the Overview instead of receiving its own section.
     */
    private static function renderCompactModule(PhpInfoCompactModule $module): Span
    {
        $enabled = true;
        $facts = [];
        $state = '';

        foreach ($module->tiles as $tile) {
            $facts[] = "{$tile->label}: {$tile->displayValue}";

            if ($tile->kind === PhpInfoTile::KIND_PILL_MUTED) {
                $enabled = false;

                continue;
            }

            if ($state === '' && $tile->kind !== PhpInfoTile::KIND_PILL_SUCCESS) {
                $state = $tile->displayValue;
            }
        }

        $summary = implode(' · ', $facts);

        if ($state === '') {
            $state = $enabled ? 'on' : 'off';
        }

        return Span::tag()
            ->addDataAttribute('section', $module->title)
            ->addDataAttribute('yii-debug-phpinfo-compact-module', true)
            ->class('yii-debug-ext-pill ' . ($enabled ? 'is-on' : 'is-off'))
            ->id($module->slug)
            ->title($summary)
            ->html(
                Span::tag()
                    ->addAriaAttribute('hidden', 'true')
                    ->class('yii-debug-ext-pill-dot'),
                Span::tag()
                    ->class('yii-debug-ext-pill-label')
                    ->content($module->title),
                Span::tag()
                    ->class('yii-debug-ext-pill-state')
                    ->content($state),
                Span::tag()
                    ->class('yii-debug-sr-only')
                    ->content($summary),
            );
    }

    /**
     * Renders the "Loaded extensions" disclosure holding every summarized module, bucketed by
     * {@see PhpInfoModuleGroup}.
     *
     * @param list<PhpInfoCompactModule> $modules Modules summarized in the Overview.
     */
    private static function renderCompactModules(array $modules): Details
    {
        $groupedModules = PhpInfoModuleGroup::bucket(
            $modules,
            static fn(PhpInfoCompactModule $module): string => $module->title,
        );

        $groups = [];

        foreach ($groupedModules as $label => $groupModules) {
            if ($groupModules === []) {
                continue;
            }

            $cards = [];

            foreach ($groupModules as $module) {
                $cards[] = self::renderCompactModule($module);
            }

            $groups[] = Section::tag()
                ->addAriaAttribute('label', $label)
                ->class('yii-debug-phpinfo-extension-group')
                ->html(
                    Header::tag()
                        ->class('yii-debug-phpinfo-extension-group-head')
                        ->html(
                            Span::tag()->content($label),
                            Span::tag()
                                ->addDataAttribute('yii-debug-phpinfo-extension-group-count', true)
                                ->addDataAttribute('yii-debug-phpinfo-total', count($groupModules))
                                ->class('yii-debug-phpinfo-extension-group-count')
                                ->content((string) count($groupModules)),
                        ),
                    Div::tag()
                        ->class('yii-debug-ext-strip')
                        ->html(...$cards),
                );
        }

        return Details::tag()
            ->addAriaAttribute('label', 'Loaded extensions')
            ->addDataAttribute('yii-debug-phpinfo-extensions', true)
            ->class('yii-debug-disclosure yii-debug-phpinfo-extensions')
            ->html(
                Summary::tag()
                    ->class('yii-debug-disclosure-summary')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-disclosure-title')
                            ->content('Loaded extensions'),
                        Disclosure::hint(),
                    ),
                Div::tag()
                    ->class('yii-debug-phpinfo-extension-groups')
                    ->html(...$groups),
            );
    }

    /**
     * Renders the Configure Command details disclosure; empty string when the command is empty.
     *
     * @param string $command Configure Command value reported by {@see phpinfo()}.
     */
    private static function renderConfigureCommand(string $command): string
    {
        if ($command === '') {
            return '';
        }

        return Details::tag()
            ->class('yii-debug-disclosure')
            ->html(
                Summary::tag()
                    ->class('yii-debug-disclosure-summary')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-disclosure-title')
                            ->content('Configure Command'),
                        Disclosure::hint(),
                    ),
                Div::tag()
                    ->class('yii-debug-disclosure-body')
                    ->html(Pre::tag()->content($command)),
            )
            ->render();
    }

    /**
     * Renders the main column (search input + Overview section + Configure Command details + raw modules HTML).
     *
     * @param PhpInfoView $view Normalized page model.
     */
    private static function renderMain(PhpInfoView $view): Div
    {
        $heroChildren = [];

        foreach ($view->sections as $section) {
            $heroChildren[] = self::renderSection($section);
        }

        if ($view->compactModules !== []) {
            $heroChildren[] = self::renderCompactModules($view->compactModules);
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
     *
     * @param PhpInfoSection $section Section to render.
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
     *
     * @param PhpInfoTile $tile Tile to render.
     */
    private static function renderTile(PhpInfoTile $tile): Div
    {
        $class = 'yii-debug-phpinfo-overview-hero-metric';

        if (
            mb_strlen($tile->rawValue) > 48
            || in_array(
                $tile->kind,
                [PhpInfoTile::KIND_PATH, PhpInfoTile::KIND_PATH_LIST, PhpInfoTile::KIND_TOKEN_LIST],
                true,
            )
        ) {
            $class .= ' is-wide';
        }

        return Div::tag()
            ->class($class)
            ->html(
                Dt::tag()->content($tile->label),
                Dd::tag()->html(self::renderTileValue($tile)),
            );
    }

    /**
     * Renders the inner `<dd>` content for one tile.
     *
     * @param PhpInfoTile $tile Tile whose value is being rendered.
     */
    private static function renderTileValue(PhpInfoTile $tile): string
    {
        if ($tile->kind === PhpInfoTile::KIND_PILL_SUCCESS || $tile->kind === PhpInfoTile::KIND_PILL_MUTED) {
            return Span::tag()
                ->addDataAttribute('variant', $tile->kind === PhpInfoTile::KIND_PILL_SUCCESS ? 'success' : 'muted')
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
     * @param list<PhpInfoTocEntry> $entries TOC entries in display order, Overview first.
     * @param int $compactModuleCount Number of modules summarized in the Overview instead of getting a TOC entry.
     */
    private static function renderToc(array $entries, int $compactModuleCount): Aside
    {
        $moduleEntries = [];
        $overviewItem = null;

        foreach ($entries as $entry) {
            if ($entry->slug === 'phpinfo-overview') {
                $overviewItem = Li::tag()->html(self::renderTocLink($entry, true));

                continue;
            }

            $moduleEntries[] = $entry;
        }

        $groupedEntries = PhpInfoModuleGroup::bucket(
            $moduleEntries,
            static fn(PhpInfoTocEntry $entry): string => $entry->title,
        );
        $moduleCount = count($moduleEntries);
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
                                ->addAriaAttribute('label', self::formatModuleCount(count($groupEntries)))
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
                        Span::tag()->content((string) ($moduleCount + $compactModuleCount)),
                        Span::tag()->content('modules'),
                        $compactModuleCount > 0
                            ? Span::tag()
                                ->class('yii-debug-phpinfo-toc-title-note')
                                ->content($compactModuleCount . ' in Overview')
                            : '',
                    ),
                ...$navigation,
            );
    }

    /**
     * Renders one TOC anchor, flagging the active entry for both styling and assistive technology.
     *
     * @param PhpInfoTocEntry $entry Entry to link.
     * @param bool $active Whether the entry is the current destination.
     */
    private static function renderTocLink(PhpInfoTocEntry $entry, bool $active = false): A
    {
        $link = A::tag()
            ->addDataAttribute('toc-target', $entry->slug)
            ->class('yii-debug-phpinfo-toc-link' . ($active ? ' is-active' : ''))
            ->content($entry->title)
            ->href("#{$entry->slug}");

        return $active ? $link->addAriaAttribute('current', 'page') : $link;
    }
}
