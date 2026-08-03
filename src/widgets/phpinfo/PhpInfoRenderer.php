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

use function count;

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
                            ->content('Filter PHP modules and directives'),
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
                            ->placeholder('Search modules or directives…'),
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
                            ->content('No modules or directives match this query.'),
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
        $items = [];

        foreach ($entries as $entry) {
            $isOverview = $entry->slug === 'phpinfo-overview';

            $link = A::tag()
                ->addDataAttribute('toc-target', $entry->slug)
                ->class('yii-debug-phpinfo-toc-link' . ($isOverview ? ' is-active' : ''))
                ->content($entry->title)
                ->href("#{$entry->slug}");

            if ($isOverview) {
                $link = $link->addAriaAttribute('current', 'page');
            }

            $items[] = Li::tag()->html(
                $link,
            );
        }

        $moduleCount = count($entries) > 0 ? count($entries) - 1 : 0;

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
                Ul::tag()->class('yii-debug-phpinfo-toc-list')->html(...$items),
            );
    }
}
