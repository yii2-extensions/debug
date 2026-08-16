<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

use PHPForge\Debug\Helper\Vocabulary;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};
use Yii;
use yii\debug\widgets\Tabs;
use yii\helpers\VarDumper;

use function htmlspecialchars;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Renders the Request panel detail view.
 *
 * Stateless static helpers: the public entry points take a typed {@see RequestHero}, {@see RequestSection}, or
 * {@see RequestView} and return ready-to-echo HTML strings. Concentrates name/value table rendering, status-pill
 * tinting, filter-affordance wiring, and tab navigation in one testable place.
 */
final class RequestSectionRenderer
{
    /**
     * Renders the hero header: method pill, URL, status pill, and the `ip` / `time` / `durationMs` / flags meta strip.
     */
    public static function renderHero(RequestHero $hero): string
    {
        $line = [];

        if ($hero->method !== '') {
            $line[] = Span::tag()
                ->class('yii-debug-request-hero-method yii-debug-verb-' . Vocabulary::verb($hero->method))
                ->content($hero->method);
        }

        $line[] = Span::tag()
            ->class('yii-debug-request-hero-url')
            ->title($hero->url)
            ->content($hero->url);

        if ($hero->statusCode > 0) {
            $line[] = Span::tag()
                ->class("yii-debug-snapshot-status yii-debug-status-{$hero->statusVariant}")
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
     * Renders a single name/value section as `<header>` + `<table>`, or as an empty-state `<p>` when the section has
     * no entries.
     */
    public static function renderSection(RequestSection $section): string
    {
        $header = self::renderSectionHeader($section);

        if ($section->entries === []) {
            $emptyState = P::tag()
                ->class('yii-debug-table-empty')
                ->content('No data')
                ->render();

            return "{$header}{$emptyState}";
        }

        $table = self::renderSectionTable($section);

        return "{$header}{$table}";
    }

    /**
     * Renders the full tab strip plus the per-tab content panels, wrapping the sections returned by
     * {@see renderSection()}.
     *
     * @param list<RequestTab> $tabs Tabs in display order.
     */
    public static function renderTabs(array $tabs): string
    {
        $items = [];

        foreach ($tabs as $tab) {
            $content = '';

            foreach ($tab->sections as $section) {
                $content .= self::renderSection($section);
            }

            $items[] = ['label' => $tab->label, 'content' => $content];
        }

        return Tabs::render('request', 'Request data', $items);
    }

    /**
     * Renders one row of the section table: name in the `<th>`, value dumped via {@see VarDumper::dumpAsString()} in
     * the `<td>` with `htmlspecialchars` (`ENT_QUOTES | ENT_SUBSTITUTE`) escaping.
     *
     * The `ENT_SUBSTITUTE` flag mirrors what the legacy view did, so the rendered DOM is identical for already-captured
     * request snapshots.
     */
    private static function renderRow(int|string $name, mixed $value): Tr
    {
        $charset = Yii::$app->charset;

        $valueText = VarDumper::dumpAsString($value);

        $escaped = htmlspecialchars($valueText, ENT_QUOTES | ENT_SUBSTITUTE, $charset, true);

        return Tr::tag()
            ->html(
                Th::tag()->scope('row')->content((string) $name),
                Td::tag()->html($escaped),
            );
    }

    /**
     * Builds the `<header>` with the section caption and the optional filter input.
     */
    private static function renderSectionHeader(RequestSection $section): string
    {
        $children = [H2::tag()->content($section->caption)];

        if ($section->filterable && $section->entries !== []) {
            $children[] = InputSearch::tag()
                ->addAriaAttribute('label', "Filter {$section->caption}")
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
                        Thead::tag()->html(
                            Tr::tag()->html(
                                Th::tag()->scope('col')->content('Name'),
                                Th::tag()->scope('col')->content('Value'),
                            ),
                        ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }
}
