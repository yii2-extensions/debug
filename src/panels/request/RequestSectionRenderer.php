<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\H3;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii;
use yii\helpers\VarDumper;

use function htmlspecialchars;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Renders the Request panel detail view on top of `ui-awesome/html` builders.
 *
 * Stateless static helpers; the public entry points take a typed {@see RequestHero}, {@see RequestSection} or
 * {@see RequestView} and return ready-to-echo HTML strings. Concentrates name/value table rendering, status pill
 * tinting, filter affordance wiring and tab navigation in one testable place.
 *
 * Usage example:
 * ```php
 * echo \yii\debug\panels\request\RequestSectionRenderer::renderHero($view->hero);
 * echo \yii\debug\panels\request\RequestSectionRenderer::renderTabs($view->tabs);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class RequestSectionRenderer
{
    /**
     * Renders the hero header (method pill + url + status pill, plus the ip/time/duration/flags meta strip).
     */
    public static function renderHero(RequestHero $hero): string
    {
        $line = [];

        if ($hero->method !== '') {
            $line[] = Span::tag()
                ->class('yii-debug-request-hero-method')
                ->content($hero->method);
        }

        $line[] = Span::tag()
            ->class('yii-debug-request-hero-url')
            ->title($hero->url)
            ->content($hero->url);

        if ($hero->statusCode > 0) {
            $line[] = Span::tag()
                ->class("yii-debug-snapshot-status yii-debug-snapshot-status-{$hero->statusVariant}")
                ->content((string) $hero->statusCode);
        }

        $meta = [];

        foreach ([$hero->ip, $hero->time, $hero->durationMs] as $piece) {
            if ($piece !== '') {
                $meta[] = Span::tag()
                    ->content($piece);
            }
        }

        foreach ($hero->flags as $flag) {
            $meta[] = Span::tag()
                ->class('yii-debug-snapshot-tag')
                ->content($flag);
        }

        return Header::tag()
            ->class('yii-debug-request-hero')
            ->html(
                Div::tag()->class('yii-debug-request-hero-line')->html(...$line),
                Div::tag()->class('yii-debug-request-hero-meta')->html(...$meta),
            )
            ->render();
    }

    /**
     * Renders a single name/value section as `<header>` + `<table>` (or an empty-state `<p>` when the section has
     * no entries).
     */
    public static function renderSection(RequestSection $section): string
    {
        $header = self::renderSectionHeader($section);

        if ($section->entries === []) {
            return $header . P::tag()
                ->class('yii-debug-table-empty')
                ->content('No data')
                ->render();
        }

        return $header . self::renderSectionTable($section);
    }

    /**
     * Renders the full tab strip plus the per-tab content panels, wrapping the sections returned by `renderSection`.
     *
     * @param list<RequestTab> $tabs
     */
    public static function renderTabs(array $tabs): string
    {
        return self::renderTabNav($tabs) . self::renderTabPanels($tabs);
    }

    /**
     * Renders one row of the section table — name in the `<th>`, value dumped via `VarDumper::dumpAsString()` in the
     * `<td>` with the same escaping the panel has always used (single-quoted scalars, multi-line arrays, …).
     */
    private static function renderRow(int|string $name, mixed $value): Tr
    {
        $charset = Yii::$app->charset;

        $valueText = VarDumper::dumpAsString($value);
        // `htmlspecialchars` with `ENT_SUBSTITUTE` mirrors what the legacy view did so the rendered DOM is identical
        // for already-captured request snapshots.
        $escaped = htmlspecialchars($valueText, ENT_QUOTES | ENT_SUBSTITUTE, $charset, true);

        return Tr::tag()
            ->html(
                Th::tag()->content((string) $name),
                Td::tag()->html($escaped),
            );
    }

    /**
     * Builds the `<header>` with the section caption and the optional filter input.
     */
    private static function renderSectionHeader(RequestSection $section): string
    {
        $children = [H3::tag()->content($section->caption)];

        if ($section->filterable && $section->entries !== []) {
            $children[] = InputSearch::tag()
                ->addAriaAttribute('label', 'Filter ' . $section->caption)
                ->addDataAttribute('yii-debug-filter', true)
                ->class('yii-debug-filter-input')
                ->placeholder('Filter…');
        }

        return Header::tag()
            ->class('yii-debug-section-header')
            ->html(...$children)
            ->render();
    }

    /**
     * Builds the section table with the name/value rows.
     */
    private static function renderSectionTable(RequestSection $section): string
    {
        $rows = [];

        foreach ($section->entries as $name => $value) {
            $rows[] = self::renderRow($name, $value);
        }

        $wrap = Div::tag()
            ->class('yii-debug-table-wrap');

        if ($section->filterable) {
            $wrap = $wrap->addDataAttribute('yii-debug-filter-target', true);
        }

        return $wrap
            ->html(
                Table::tag()
                    ->class('yii-debug-table yii-debug-table-mono')
                    ->style(['table-layout' => 'fixed'])
                    ->html(
                        Thead::tag()->html(Tr::tag()->html(Th::tag()->content('Name'), Th::tag()->content('Value'))),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders the `<ul class="yii-debug-tabs">` strip with one `<li>`/`<a>` per tab.
     *
     * @param list<RequestTab> $tabs
     */
    private static function renderTabNav(array $tabs): string
    {
        $items = [];

        foreach ($tabs as $k => $tab) {
            $isActive = $k === 0;

            $items[] = Li::tag()
                ->class('yii-debug-tab')
                ->html(
                    A::tag()
                        ->addAttribute('aria-controls', "r-tab-{$k}")
                        ->addAttribute('aria-selected', $isActive ? 'true' : 'false')
                        ->addAttribute('data-yii-debug-toggle', 'tab')
                        ->addAttribute('role', 'tab')
                        ->class($isActive ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link')
                        ->content($tab->label)
                        ->href("#r-tab-{$k}"),
                );
        }

        return Ul::tag()
            ->class('yii-debug-tabs')
            ->html(...$items)
            ->render();
    }

    /**
     * Renders the per-tab content panels. The first tab is marked active; the rest are hidden until the toggle JS
     * activates them.
     *
     * @param list<RequestTab> $tabs
     */
    private static function renderTabPanels(array $tabs): string
    {
        $panels = [];

        foreach ($tabs as $k => $tab) {
            $sectionsHtml = '';

            foreach ($tab->sections as $section) {
                $sectionsHtml .= self::renderSection($section);
            }

            $panels[] = Div::tag()
                ->class($k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel')
                ->id("r-tab-{$k}")
                ->html($sectionsHtml);
        }

        return Div::tag()
            ->class('yii-debug-tab-content')
            ->html(...$panels)
            ->render();
    }
}
