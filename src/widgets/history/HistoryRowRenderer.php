<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryScale, HistorySummary};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii;
use yii\debug\GridViewConfig;
use yii\debug\models\search\DebugSearch;
use yii\debug\Module;
use yii\debug\panels\DbPanel;
use yii\helpers\Url;

/**
 * Renders the History index summary header + the per-cell HTML consumed by the GridView columns and the typed
 * `rowOptions` builder.
 *
 * Stateless static helpers; every method takes a typed {@see HistoryRow} or {@see HistorySummary} and returns a
 * ready-to-echo HTML string (or, for the row options builder, the attribute map the GridView consumes for `<tr>`).
 */
final class HistoryRowRenderer
{
    /**
     * Builds the `rowOptions` attribute map for one captured-request row. The `data-*` attributes feed the sidebar's
     * history cursor.
     *
     * @return array<string, mixed>
     */
    public static function buildRowOptions(HistoryRow $row, DebugSearch $searchModel): array
    {
        return HistoryCellRenderer::buildRowAttributes($row, $searchModel->isCodeCritical($row->statusCode));
    }

    /**
     * Renders the AJAX column cell (`'Yes'` / `'No'`).
     */
    public static function renderAjaxCell(HistoryRow $row): string
    {
        return HistoryCellRenderer::renderAjaxCell($row);
    }

    /**
     * Renders the duration column cell (`'X ms'` or `'(not set)'` muted placeholder when missing), with a micro-gauge
     * rail scaled against the page maximum when one exists.
     *
     * @param HistoryRow $row Typed history row.
     * @param float $maxProcessingTime Page maximum in seconds ({@see HistoryScale::$maxProcessingTime}).
     */
    public static function renderDurationCell(HistoryRow $row, float $maxProcessingTime): string
    {
        return HistoryCellRenderer::renderDurationCell($row, $maxProcessingTime);
    }

    /**
     * Renders the memory column cell (`'X.XXX MB'` or `'(not set)'`), with a micro-gauge rail scaled against the page
     * maximum when one exists.
     *
     * @param HistoryRow $row Typed history row.
     * @param int $maxPeakMemory Page maximum in bytes ({@see HistoryScale::$maxPeakMemory}).
     */
    public static function renderMemoryCell(HistoryRow $row, int $maxPeakMemory): string
    {
        return HistoryCellRenderer::renderMemoryCell($row, $maxPeakMemory);
    }

    /**
     * Renders the method column cell as vocabulary-colored text, or an empty string when the method was not captured.
     */
    public static function renderMethodCell(HistoryRow $row): string
    {
        return HistoryCellRenderer::renderMethodCell($row);
    }

    /**
     * Renders the SQL-query column cell (count + warning chip + deep-link to the DB panel).
     */
    public static function renderSqlCountCell(HistoryRow $row, DbPanel $dbPanel): string
    {
        return HistoryCellRenderer::renderSqlCountCell(
            $row,
            Url::to(Module::route('view', ['panel' => 'db', 'tag' => $row->tag])),
            $dbPanel->isQueryCountCritical($row->sqlCount),
            $dbPanel->criticalQueryThreshold ?? 0,
        );
    }

    /**
     * Renders the summary header (`<header class="yii-debug-grid-summary">`) with the request total and the
     * status-bucket pills.
     */
    public static function renderSummary(HistorySummary $summary): string
    {
        if ($summary->totalRequests === 0) {
            return '';
        }

        $requestLabel = $summary->totalRequests === 1 ? 'captured request' : 'captured requests';

        $children = [
            Span::tag()->html(
                Strong::tag()->content((string) $summary->totalRequests),
                " {$requestLabel}",
            ),
        ];

        foreach ($summary->statusBuckets as $bucket) {
            $children[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $children[] = A::tag()
                ->class("yii-debug-grid-summary-stat-{$bucket->variant}")
                ->href(Url::to(Module::route('index', ['Debug[statusCode]' => $bucket->sampleCode])))
                ->title("Filter to {$bucket->label} responses (sample {$bucket->sampleCode})")
                ->html(Strong::tag()->content((string) $bucket->count), " {$bucket->label}");
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$children, ...[GridViewConfig::pageSizeSelectorHtml()])
            ->render();
    }

    /**
     * Renders the request-tag column cell as a link to the panel view.
     */
    public static function renderTagCell(HistoryRow $row): string
    {
        return A::tag()
            ->class('yii-debug-tag-link')
            ->href(Url::to(Module::route('view', ['tag' => $row->tag])))
            ->content($row->tag)
            ->render();
    }

    /**
     * Renders the time column cell — compact `HH:MM:SS` with a full `yyyy-MM-dd HH:mm:ss` tooltip on hover.
     */
    public static function renderTimeCell(HistoryRow $row): string
    {
        if ($row->time === 0.0) {
            return Span::tag()
                ->class('yii-debug-not-set')
                ->content('(not set)')
                ->render();
        }

        $formatter = Yii::$app->formatter;
        $timestamp = (int) $row->time;

        $full = $formatter->asDatetime($timestamp, 'yyyy-MM-dd HH:mm:ss');
        $compact = $formatter->asTime($timestamp, 'HH:mm:ss');

        return Span::tag()
            ->class('yii-debug-nowrap')
            ->title($full)
            ->content($compact)
            ->render();
    }

    /**
     * Renders the URL column cell with a hover-truncate wrapper.
     */
    public static function renderUrlCell(HistoryRow $row): string
    {
        return HistoryCellRenderer::renderUrlCell($row);
    }
}
