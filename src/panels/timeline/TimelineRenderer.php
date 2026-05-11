<?php

declare(strict_types=1);

namespace yii\debug\panels\timeline;

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputNumber, InputText};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Em, Label, Span, Strong};
use UIAwesome\Html\Root\{Footer, Header};
use UIAwesome\Html\Sectioning\Section;
use yii\debug\models\timeline\{DataProvider, Search, Svg};
use yii\debug\panels\TimelinePanel;
use yii\helpers\Url;

use function count;
use function is_array;
use function is_string;
use function number_format;
use function rtrim;
use function sprintf;

/**
 * Renders the Timeline panel detail view on top of `ui-awesome/html` builders.
 *
 * Stateless static helpers; the public entry points take the typed `TimelinePanel` plus the search model and data
 * provider and return ready-to-echo HTML strings. Concentrates summary header layout, filter-form wiring, chart row
 * iteration, ruler axis, memory footer composition and the empty/short-request hint in one testable place.
 *
 * Usage example:
 * ```php
 * echo \yii\debug\panels\timeline\TimelineRenderer::renderSummary($panel, $dataProvider);
 * echo \yii\debug\panels\timeline\TimelineRenderer::renderFilterForm($panel, $searchModel);
 * echo \yii\debug\panels\timeline\TimelineRenderer::renderChart($panel, $dataProvider);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class TimelineRenderer
{
    private const BYTES_PER_MB = 1048576;

    /**
     * Renders the timeline chart (ruler axis + per-span rows + optional memory footer); returns empty string when the
     * data provider has no spans so the empty hint can take over without duplicate markup.
     */
    public static function renderChart(TimelinePanel $panel, DataProvider $dataProvider): string
    {
        if ($dataProvider->models === []) {
            return '';
        }

        $svg = $panel->getSvg();

        $children = [
            self::renderAxis($dataProvider),
            self::renderRows($dataProvider),
        ];

        if ($svg->hasPoints()) {
            $children[] = self::renderMemoryFooter($panel, $svg);
        }

        return Section::tag()
            ->class('yii-debug-tl')
            ->html(...$children)
            ->render();
    }

    /**
     * Renders the empty-state hint surfaced when no spans matched the active filter. The hint points the developer at
     * the Profiling panel which presents the same data as a sortable list. Returns empty string when the chart has data
     * the chart already conveys the request shape.
     */
    public static function renderEmptyHint(TimelinePanel $panel, DataProvider $dataProvider): string
    {
        if ($dataProvider->models !== []) {
            return '';
        }

        $moduleId = $panel->module !== null ? $panel->module->getUniqueId() : 'debug';

        $profilingUrl = [
            "/{$moduleId}/default/view",
            'panel' => 'profiling',
            'tag' => $panel->tag,
        ];

        return Div::tag()
            ->class('yii-debug-tl-hint')
            ->html(
                P::tag()
                    ->class('yii-debug-tl-hint-title')
                    ->content('No spans matched your filter.'),
                P::tag()
                    ->class('yii-debug-tl-hint-body')
                    ->html(
                        'The timeline is most useful for requests that take hundreds of milliseconds, where you can ',
                        Em::tag()->content('see'),
                        ' which operations dominate. For quick requests the ',
                        A::tag()->href(Url::to($profilingUrl))->content('Profiling panel'),
                        ' presents the same data as a sortable list — easier to scan.',
                    ),
            )
            ->render();
    }

    /**
     * Renders the filter form (min-duration number + category text + apply button). Hidden inputs preserve 'r' /
     * 'panel' / 'tag' so submitting the form lands back on the current snapshot.
     */
    public static function renderFilterForm(TimelinePanel $panel, Search $searchModel): string
    {
        return Form::tag()
            ->action(Url::to($panel->getUrl()))
            ->class('yii-debug-tl-filter')
            ->html(
                InputHidden::tag()
                    ->name('r')
                    ->value('debug/default/view'),
                InputHidden::tag()
                    ->name('panel')
                    ->value('timeline'),
                InputHidden::tag()
                    ->name('tag')
                    ->value($panel->tag),
                Div::tag()
                    ->class('yii-debug-tl-field')
                    ->html(
                        Label::tag()
                            ->content('Min duration (ms)')
                            ->for('tl-duration'),
                        InputNumber::tag()
                            ->id('tl-duration')
                            ->min(0)
                            ->name('Search[duration]')
                            ->placeholder('0')
                            ->step(0.1)
                            ->value($searchModel->duration),
                    ),
                Div::tag()
                    ->class('yii-debug-tl-field yii-debug-tl-field-grow')
                    ->html(
                        Label::tag()
                            ->content('Category')
                            ->for('tl-category'),
                        InputText::tag()
                            ->id('tl-category')
                            ->name('Search[category]')
                            ->placeholder('yii\\db\\Command::query')
                            ->value($searchModel->category),
                    ),
                Button::tag()
                    ->class('yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm')
                    ->content('Apply')
                    ->type('submit'),
            )
            ->method('get')
            ->render();
    }

    /**
     * Renders the top summary header (total ms + peak memory + span count).
     */
    public static function renderSummary(TimelinePanel $panel, DataProvider $dataProvider): string
    {
        $totalDuration = $panel->getDuration();

        $peakMemoryMB = self::bytesToMb($panel->getMemory());

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()->html(Strong::tag()->content(number_format($totalDuration)), ' ms total'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content($peakMemoryMB), ' peak memory'),
                Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
                Span::tag()->html(Strong::tag()->content((string) count($dataProvider->models)), ' spans'),
            )
            ->render();
    }

    /**
     * Formats a byte count as `"X.XX MB"` with two-decimal precision.
     */
    private static function bytesToMb(float|int $bytes): string
    {
        return sprintf('%.2f MB', $bytes / self::BYTES_PER_MB);
    }

    private static function percent(float|int|string $value): string
    {
        $float = (float) $value;

        $rendered = sprintf('%.3f', $float);
        $rendered = rtrim($rendered, '0');
        $rendered = rtrim($rendered, '.');

        return $rendered . '%';
    }

    /**
     * Renders the ruler axis (top tick strip with `Xms` labels positioned via inline `left:<pct>%`).
     */
    private static function renderAxis(DataProvider $dataProvider): Header
    {
        $ticks = [];

        foreach ($dataProvider->getRulers() as $ms => $left) {
            $ticks[] = Span::tag()
                ->class('yii-debug-tl-tick')
                ->content(sprintf('%.1f ms', $ms))
                ->style(['left' => self::percent($left)]);
        }

        return Header::tag()
            ->class('yii-debug-tl-axis')
            ->html(...$ticks);
    }

    /**
     * Renders the memory footer (track + SVG memory line + peak-memory chip).
     */
    private static function renderMemoryFooter(TimelinePanel $panel, Svg $svg): Footer
    {
        $peakMemoryMB = self::bytesToMb((float) $panel->getMemory());

        return Footer::tag()
            ->class('yii-debug-tl-memory')
            ->html(
                Span::tag()
                    ->class('yii-debug-tl-memory-label')
                    ->content('Memory'),
                Div::tag()
                    ->class('yii-debug-tl-memory-track')
                    ->html((string) $svg)
                    ->style(['height' => sprintf('%dpx', $svg->y)]),
                Span::tag()
                    ->class('yii-debug-tl-memory-peak')
                    ->content($peakMemoryMB),
            );
    }

    /**
     * Renders one span row (label column with depth indent + dot + name; track column with the positioned bar and
     * its inline duration chip).
     */
    private static function renderRow(TimelineSpanRow $row): Div
    {
        return Div::tag()
            ->addAttribute('role', 'listitem')
            ->class("yii-debug-tl-row yii-debug-tl-row-{$row->variant}")
            ->html(
                Div::tag()
                    ->class('yii-debug-tl-label')
                    ->style(['--depth' => (string) $row->depth])
                    ->html(
                        Span::tag()->class('yii-debug-tl-dot')->addAttribute('aria-hidden', 'true'),
                        Span::tag()->class('yii-debug-tl-name')->content($row->category),
                    ),
                Div::tag()
                    ->class('yii-debug-tl-track')
                    ->html(
                        Div::tag()
                            ->class('yii-debug-tl-bar')
                            ->style([
                                'left' => $row->cssLeft . '%',
                                'width' => $row->cssWidth . '%',
                            ])
                            ->html(
                                Span::tag()
                                    ->class('yii-debug-tl-bar-duration')
                                    ->content(sprintf('%.1f ms', $row->duration)),
                            ),
                    ),
            )
            ->title($row->tooltip);
    }

    /**
     * Renders the span-rows container with one row per captured model.
     */
    private static function renderRows(DataProvider $dataProvider): Div
    {
        $rows = [];

        foreach ($dataProvider->models as $model) {
            if (!is_array($model)) {
                continue;
            }

            $stringKeyed = [];

            foreach ($model as $key => $value) {
                if (is_string($key)) {
                    $stringKeyed[$key] = $value;
                }
            }

            $rows[] = self::renderRow(TimelineSpanRow::from($stringKeyed));
        }

        return Div::tag()
            ->class('yii-debug-tl-rows')
            ->addAttribute('role', 'list')
            ->html(...$rows);
    }
}
