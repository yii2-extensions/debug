<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Article;
use yii\debug\helpers\{Avatar, Fqcn};

use function array_is_list;
use function count;
use function date;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function sprintf;

/**
 * Renders the typed Queue panel detail view on top of `ui-awesome/html` builders.
 *
 * Stateless static helpers; the public entry points take a typed {@see QueueSummary} or {@see JobRecord} and return
 * UIAwesome component trees. Keeps the detail view focused on page-level scaffolding while concentrating render logic
 *  (status pills, driver badges, payload tree, component tabs, time formatting) in one testable place.
 *
 * Usage example:
 * ```php
 * echo \yii\debug\panels\queue\QueueCardRenderer::renderSummaryHeader($summary);
 * echo \yii\debug\panels\queue\QueueCardRenderer::renderItem($record);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueueCardRenderer
{
    /**
     * Maximum number of characters shown for inline string values before they get truncated with an ellipsis. The
     * full value is preserved in a `title` tooltip so the developer can hover to read it.
     */
    private const int STRING_PREVIEW_LIMIT = 80;

    /**
     * Renders the async-driver hint banner shown above the cards when at least one record was emitted by a driver
     * that runs jobs out of process. Returns `null` when every record came from a sync driver; no banner needed.
     */
    public static function renderAsyncHint(QueueSummary $summary): Div|null
    {
        $asyncDrivers = [];

        foreach ($summary->records as $record) {
            if ($record->isAsync && $record->driverName !== '' && !in_array($record->driverName, $asyncDrivers, true)) {
                $asyncDrivers[] = $record->driverName;
            }
        }

        if ($asyncDrivers === []) {
            return null;
        }

        $list = implode(', ', $asyncDrivers);

        return Div::tag()
            ->class('yii-debug-queue-hint')
            ->html(
                Strong::tag()->content('Async driver: ' . $list . '.'),
                ' Push events show here, but jobs run in a separate worker process; see the History sidebar for ',
                Strong::tag()->content('CLI'),
                ' debug snapshots that capture the matching exec/error events.',
            );
    }

    /**
     * Renders a single job event as an `<article class="yii-debug-queue-card">` ready to drop into the detail view.
     */
    public static function renderItem(JobRecord $record): Article
    {
        $children = [self::renderHead($record)];

        if ($record->payloadFields !== []) {
            $children[] = Div::tag()
                ->class('yii-debug-queue-payload')
                ->html(self::renderPayloadTree($record->payloadFields));
        }

        if ($record->error !== '') {
            $children[] = Div::tag()
                ->class('yii-debug-queue-error')
                ->html(
                    Strong::tag()->content('Error: '),
                    $record->error,
                );
        }

        $meta = self::renderMeta($record);

        if ($meta !== null) {
            $children[] = $meta;
        }

        return Article::tag()->class('yii-debug-queue-card')->html(...$children);
    }

    /**
     * Renders the summary header showing total events, pushed/executed/errors counts and an optional warning chip
     * when at least one job failed.
     */
    public static function renderSummaryHeader(QueueSummary $summary): Div
    {
        $children = [
            Span::tag()
                ->html(
                    Strong::tag()->content((string) $summary->totalPushed()),
                    ' pushed',
                ),
        ];

        if ($summary->totalExecuted() > 0) {
            $children[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $children[] = Span::tag()
                ->html(
                    Strong::tag()->content((string) $summary->totalExecuted()),
                    ' executed',
                );
        }

        if ($summary->hasErrors()) {
            $children[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $children[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-danger')
                ->html(
                    Strong::tag()->content((string) $summary->totalErrors()),
                    ' failed',
                );
        }

        return Div::tag()->class('yii-debug-grid-summary')->html(...$children);
    }

    /**
     * Returns the uppercased first letter of the short class name, falling back to `?` when empty.
     */
    private static function initialFor(string $jobClass): string
    {
        $shortName = Fqcn::shortName($jobClass);

        if ($shortName === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($shortName, 0, 1));
    }

    private static function metaItem(string $label, string $value): Span
    {
        return Span::tag()
            ->class('yii-debug-queue-meta-item')
            ->addDataAttribute('field', $label)
            ->html(
                Span::tag()->class('yii-debug-queue-meta-label')->content($label),
                Span::tag()->class('yii-debug-queue-meta-value')->content($value),
            );
    }

    /**
     * Renders an array or object value as a collapsible block. Objects carry a `__class` key that promotes the FQCN
     * into the summary header (`HelloJob {…}`); regular arrays show their length (`array(3)`). The block is collapsed
     * by default, with the `open` attribute toggled on for objects whose first child is itself a leaf; heuristic that
     * surfaces the most useful structure on first paint.
     *
     * @param array<array-key, mixed> $value
     */
    private static function renderArrayOrObjectRow(string $key, array $value): Details
    {
        $isObject = isset($value['__class']) && is_string($value['__class']);
        $isList = !$isObject && array_is_list($value);

        $children = [];

        if ($isObject) {
            $className = Fqcn::shortName($value['__class']);
            $namespace = Fqcn::namespacePart($value['__class']);
            $summaryHtml = Span::tag()
                ->class('yii-debug-queue-tree-key')
                ->content($key)
                ->render()
                . Span::tag()
                    ->class('yii-debug-queue-tree-type')
                    ->content('object')
                    ->render()
                . Span::tag()
                    ->class('yii-debug-queue-tree-class')
                    ->title($value['__class'])
                    ->content($namespace !== '' ? $namespace . '\\' . $className : $className)
                    ->render();

            unset($value['__class']);
        } else {
            $kind = $isList ? 'list' : 'array';

            $summaryHtml = Span::tag()
                ->class('yii-debug-queue-tree-key')
                ->content($key)
                ->render()
                . Span::tag()
                    ->class('yii-debug-queue-tree-type')
                    ->content($kind)
                    ->render()
                . Span::tag()
                    ->class('yii-debug-queue-tree-meta')
                    ->content(sprintf('(%d)', count($value)))
                    ->render();
        }

        if (isset($value['__truncated'])) {
            $summaryHtml .= Span::tag()
                ->class('yii-debug-queue-tree-truncated')
                ->content('truncated')
                ->render();
            unset($value['__truncated']);
        }

        foreach ($value as $childKey => $childValue) {
            $children[] = self::renderField($childKey, $childValue);
        }

        return Details::tag()
            ->class('yii-debug-queue-tree-collapse')
            ->html(
                Summary::tag()->class('yii-debug-queue-tree-summary')->html($summaryHtml),
                Div::tag()->class('yii-debug-queue-tree-children')->html(...$children),
            );
    }

    /**
     * Renders the colored avatar — same hue scheme as the mail panel, derived from the job class name.
     */
    private static function renderAvatar(JobRecord $record): Span
    {
        return Span::tag()
            ->addAriaAttribute('hidden', 'true')
            ->addAttribute('style', '--queue-hue: ' . Avatar::hueFor($record->jobClass))
            ->class('yii-debug-queue-avatar')
            ->content(self::initialFor($record->jobClass));
    }

    /**
     * Renders the driver pill (`Sync` / `Database` / `Redis` / `AMQP` / ...). Async drivers carry a different visual
     * tone via the `is-async` modifier so the developer can spot at a glance which jobs ran in-process.
     */
    private static function renderDriverPill(JobRecord $record): Span
    {
        $modifier = $record->isAsync ? 'is-async' : 'is-sync';

        return Span::tag()
            ->class("yii-debug-queue-driver yii-debug-queue-driver-{$modifier}")
            ->title($record->driverClass !== '' ? $record->driverClass : 'Unknown driver')
            ->content($record->driverName);
    }

    /**
     * Renders one tree row: scalar values render inline (key + type + value), arrays / objects collapse into a
     * `<details>` block so the developer can drill in on demand. Returns a UIAwesome builder so the parent can mix
     * rows freely.
     */
    private static function renderField(string|int $key, mixed $value): Div|Details
    {
        $keyLabel = (string) $key;

        if ($value === null) {
            return self::renderScalarRow($keyLabel, 'null', 'null', 'null');
        }

        if (is_bool($value)) {
            return self::renderScalarRow($keyLabel, 'bool', $value ? 'true' : 'false', 'bool');
        }

        if (is_int($value) || is_float($value)) {
            return self::renderScalarRow($keyLabel, is_int($value) ? 'int' : 'float', (string) $value, 'number');
        }

        if (is_string($value)) {
            return self::renderStringRow($keyLabel, $value);
        }

        if (is_array($value)) {
            return self::renderArrayOrObjectRow($keyLabel, $value);
        }

        return self::renderScalarRow($keyLabel, 'unknown', '(unsupported)', 'unknown');
    }

    /**
     * Renders the card header: avatar, job class title with namespace prefix, status + driver pills and capture time.
     */
    private static function renderHead(JobRecord $record): Header
    {
        $shortName = Fqcn::shortName($record->jobClass);
        $namespace = Fqcn::namespacePart($record->jobClass);

        $title = [
            H2::tag()
                ->class('yii-debug-queue-class')
                ->content($shortName !== '' ? $shortName : '(unknown)'),
        ];

        if ($namespace !== '') {
            $title[] = Span::tag()
                ->class('yii-debug-queue-namespace')
                ->content("{$namespace}\\");
        }

        $pills = [self::renderStatusPill($record)];

        if ($record->driverName !== '') {
            $pills[] = self::renderDriverPill($record);
        }

        $pills[] = Span::tag()
            ->class('yii-debug-queue-time')
            ->content(date('H:i:s', (int) $record->time));

        return Header::tag()
            ->class('yii-debug-queue-card-head')
            ->html(
                self::renderAvatar($record),
                Div::tag()->class('yii-debug-queue-headline')->html(...$title),
                Div::tag()->class('yii-debug-queue-meta-pills')->html(...$pills),
            );
    }

    /**
     * Renders the meta footer (queue id, ttr / delay / priority / attempt / duration), or `null` when none of the
     * optional fields are populated. The component id is intentionally omitted — the sidebar/tab strip already surfaces
     * it and a per-card pill would be redundant.
     */
    private static function renderMeta(JobRecord $record): Div|null
    {
        $items = [];

        if ($record->jobId !== '') {
            $items[] = self::metaItem('id', $record->jobId);
        }

        if ($record->ttr !== null) {
            $items[] = self::metaItem('ttr', $record->ttr . 's');
        }

        if ($record->delay !== null && $record->delay > 0) {
            $items[] = self::metaItem('delay', $record->delay . 's');
        }

        if ($record->priority !== null) {
            $items[] = self::metaItem('priority', (string) $record->priority);
        }

        if ($record->attempt !== null) {
            $items[] = self::metaItem('attempt', '#' . $record->attempt);
        }

        if ($record->duration !== null) {
            $items[] = self::metaItem('duration', sprintf('%.1f ms', $record->duration * 1000));
        }

        if ($items === []) {
            return null;
        }

        return Div::tag()->class('yii-debug-queue-meta')->html(...$items);
    }

    /**
     * Renders the recursive payload tree. Top-level entries render as flat key/type/value rows; nested arrays / objects
     * become `<details>` blocks the developer can collapse/expand.
     *
     * @param array<string, mixed> $fields
     */
    private static function renderPayloadTree(array $fields): Div
    {
        $rows = [];

        foreach ($fields as $key => $value) {
            $rows[] = self::renderField($key, $value);
        }

        return Div::tag()
            ->class('yii-debug-queue-tree')
            ->html(...$rows);
    }

    private static function renderScalarRow(string $key, string $type, string $value, string $variant): Div
    {
        return Div::tag()
            ->class('yii-debug-queue-tree-row')
            ->html(
                Span::tag()->class('yii-debug-queue-tree-key')->content($key),
                Span::tag()->class('yii-debug-queue-tree-type')->content($type),
                Span::tag()->class("yii-debug-queue-tree-value yii-debug-queue-tree-value-{$variant}")->content($value),
            );
    }

    /**
     * Renders the status pill (`Queued` / `Done` / `Failed`).
     */
    private static function renderStatusPill(JobRecord $record): Span
    {
        $variant = JobRecord::EVENT_VARIANTS[$record->eventType]['variant'] ?? 'queued';
        $label = JobRecord::EVENT_VARIANTS[$record->eventType]['label'] ?? 'Queued';

        return Span::tag()
            ->class("yii-debug-queue-status yii-debug-queue-status-{$variant}")
            ->content($label);
    }

    private static function renderStringRow(string $key, string $value): Div
    {
        $preview = mb_strlen($value) > self::STRING_PREVIEW_LIMIT
            ? mb_substr($value, 0, self::STRING_PREVIEW_LIMIT) . '…'
            : $value;

        return Div::tag()
            ->class('yii-debug-queue-tree-row')
            ->html(
                Span::tag()
                    ->class('yii-debug-queue-tree-key')
                    ->content($key),
                Span::tag()
                    ->class('yii-debug-queue-tree-type')
                    ->content('string'),
                Span::tag()
                    ->class('yii-debug-queue-tree-value yii-debug-queue-tree-value-string')
                    ->title($value)
                    ->content('"' . $preview . '"'),
            );
    }

}
