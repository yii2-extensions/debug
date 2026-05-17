<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii;
use yii\debug\GridViewConfig;
use yii\debug\helpers\Format;
use yii\debug\models\search\DebugSearch;
use yii\debug\panels\DbPanel;
use yii\helpers\Url;

use function implode;
use function is_string;
use function number_format;
use function trim;

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
     * Builds the `rowOptions` attribute map for one captured-request row `class` carries the critical-status highlight
     * + the JS row-link hook, and the `data-*` attributes feed the sidebar's history-cursor JS.
     *
     * @return array<string, mixed>
     */
    public static function buildRowOptions(HistoryRow $row, DebugSearch $searchModel): array
    {
        $base = $searchModel->isCodeCritical($row->statusCode)
            ? GridViewConfig::rowClassFor('danger')
            : [];

        $rowClass = is_string($base['class'] ?? null) ? $base['class'] : '';

        $base['class'] = trim($rowClass . ' yii-debug-row-link');
        $base['data-href'] = Url::to(['view', 'tag' => $row->tag]);
        $base['data-yii-debug-tag'] = $row->tag;
        $base['data-yii-debug-method'] = $row->method;
        $base['data-yii-debug-url'] = $row->url;
        $base['data-yii-debug-status'] = (string) $row->statusCode;
        $base['data-yii-debug-time'] = $row->timeCompact;
        $base['data-yii-debug-ajax'] = $row->isAjax ? '1' : '';

        return $base;
    }

    /**
     * Renders the AJAX column cell (`'Yes'` / `'No'`).
     */
    public static function renderAjaxCell(HistoryRow $row): string
    {
        return $row->isAjax ? 'Yes' : 'No';
    }

    /**
     * Renders the duration column cell (`'X ms'` or `'(not set)'` muted placeholder when missing).
     */
    public static function renderDurationCell(HistoryRow $row): string
    {
        if ($row->processingTime === null) {
            return Span::tag()
                ->class('yii-debug-not-set')
                ->content('(not set)')
                ->render();
        }

        return number_format($row->processingTime * 1000) . ' ms';
    }

    /**
     * Renders the memory column cell (`'X.XXX MB'` or `'(not set)'`).
     */
    public static function renderMemoryCell(HistoryRow $row): string
    {
        if ($row->peakMemory === null) {
            return Span::tag()
                ->class('yii-debug-not-set')
                ->content('(not set)')
                ->render();
        }

        return Format::bytesToMb($row->peakMemory, 3);
    }

    /**
     * Renders the SQL-query column cell (count + warning chip + deep-link to the DB panel).
     */
    public static function renderSqlCountCell(HistoryRow $row, DbPanel $dbPanel): string
    {
        $title = "Executed {$row->sqlCount} database queries.";

        $warningParts = [];

        if ($dbPanel->isQueryCountCritical($row->sqlCount)) {
            $warningParts[] = "Too many queries. Allowed count is {$dbPanel->criticalQueryThreshold}";
        }

        if ($row->excessiveCallersCount > 0) {
            $warningParts[] = "{$row->excessiveCallersCount} "
                . ($row->excessiveCallersCount === 1 ? 'caller is' : 'callers are')
                . ' making too many calls.';
        }

        $warning = implode(' &#10;', $warningParts);

        $content = (string) $row->sqlCount;

        if ($warning !== '') {
            $content .= ' '
                . Span::tag()
                    ->title($warning)
                    ->content('⚠')
                    ->render();
        }

        return A::tag()
            ->href(Url::to(['view', 'panel' => 'db', 'tag' => $row->tag]))
            ->title($title)
            ->html($content)
            ->render();
    }

    /**
     * Renders the status-code badge cell, branching the variant on the status range.
     */
    public static function renderStatusCell(HistoryRow $row): string
    {
        $statusCode = $row->statusCode === 0 ? 200 : $row->statusCode;

        $variant = match (true) {
            $statusCode >= 200 && $statusCode < 300 => 'success',
            $row->method === 'COMMAND' && $row->statusCode === 0 => 'success',
            $statusCode >= 300 && $statusCode < 400 => 'info',
            default => 'danger',
        };

        return Span::tag()
            ->class("yii-debug-badge yii-debug-badge--{$variant}")
            ->content((string) $statusCode)
            ->render();
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

        $children = [
            Span::tag()->html(
                Strong::tag()->content((string) $summary->totalRequests),
                ' captured request' . ($summary->totalRequests === 1 ? '' : 's'),
            ),
        ];

        foreach ($summary->statusBuckets as $bucket) {
            $children[] = Span::tag()->class('yii-debug-grid-summary-sep')->content('·');
            $children[] = A::tag()
                ->class("yii-debug-grid-summary-stat-{$bucket->variant}")
                ->href(Url::to(['index', 'Debug[statusCode]' => $bucket->sampleCode]))
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
            ->href(Url::to(['view', 'tag' => $row->tag]))
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
        return Span::tag()
            ->class('yii-debug-url-cell')
            ->title($row->url)
            ->content($row->url)
            ->render();
    }
}
