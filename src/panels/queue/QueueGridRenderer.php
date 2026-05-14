<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use yii\debug\helpers\Fqcn;

use function abs;
use function date;
use function number_format;
use function sprintf;

/**
 * Renders the column cells for the Queue panel grid view.
 *
 * Stateless static helpers: each public method takes a typed {@see JobRecord} (and, for `renderJobCell`, a per-row
 * detail URL) and returns a fully-rendered HTML string suitable for a Yii GridView column `value` callback.
 */
final class QueueGridRenderer
{
    /**
     * Renders the attempt cell as `#N` for non-zero attempts, falling back to an em dash (`—`) otherwise.
     */
    public static function renderAttemptCell(JobRecord $record): string
    {
        if ($record->attempt === null || $record->attempt <= 0) {
            return '—';
        }

        return "#{$record->attempt}";
    }

    /**
     * Renders the component id cell (`'queue'` / `'queueRedis'` / ...) as plain text.
     *
     * The value is small enough that a pill would only add visual noise.
     */
    public static function renderComponentCell(JobRecord $record): string
    {
        return $record->componentId;
    }

    /**
     * Renders the driver pill (`Sync` / `Database` / `Redis` / ...).
     *
     * Async drivers carry the `is-async` modifier, so the developer can spot at a glance which jobs ran in-process.
     */
    public static function renderDriverCell(JobRecord $record): string
    {
        if ($record->driverName === '') {
            return '';
        }

        $modifier = $record->isAsync ? 'is-async' : 'is-sync';

        return Span::tag()
            ->class("yii-debug-queue-driver yii-debug-queue-driver-{$modifier}")
            ->title($record->driverClass !== '' ? $record->driverClass : 'Unknown driver')
            ->content($record->driverName)
            ->render();
    }

    /**
     * Renders the duration cell as `X.X ms` for executed/errored events, falling back to an em dash (`—`) for push
     * events that did not carry a duration.
     */
    public static function renderDurationCell(JobRecord $record): string
    {
        if ($record->duration === null) {
            return '—';
        }

        return number_format($record->duration * 1000, 1) . ' ms';
    }

    /**
     * Renders the job-id cell, wrapping the id in a monospaced link-styled span (matching the History panel's tag
     * column) so async-driver ids (UUIDs, hex strings, ...) read cleanly.
     *
     * An empty id renders as an em dash (`—`), so the column never collapses to whitespace and the dash makes it
     * obvious the queue did not return an id at push time.
     */
    public static function renderIdCell(JobRecord $record): string
    {
        if ($record->jobId === '') {
            return '—';
        }

        return Span::tag()
            ->class('yii-debug-tag-link')
            ->content($record->jobId)
            ->render();
    }

    /**
     * Renders the job-class cell as a link to the dedicated detail page.
     *
     * The short class name shows in bold; the namespace prefix renders muted underneath; the full FQCN sits in the
     * `title` attribute for hover inspection. Click drills into the typed payload tree.
     *
     * @param JobRecord $record Typed queue event record.
     * @param string $href URL of the dedicated detail page for `$record`.
     */
    public static function renderJobCell(JobRecord $record, string $href): string
    {
        $shortName = Fqcn::shortName($record->jobClass);
        $namespace = Fqcn::namespacePart($record->jobClass);

        return Div::tag()
            ->class('yii-debug-queue-grid-job')
            ->html(
                A::tag()
                    ->class('yii-debug-queue-grid-job-link')
                    ->href($href)
                    ->title($record->jobClass !== '' ? $record->jobClass : 'Mixed payload')
                    ->html(Strong::tag()->content($shortName === '' ? '—' : $shortName)),
                $namespace !== ''
                    ? Span::tag()
                        ->class('yii-debug-queue-grid-job-namespace')
                        ->content("{$namespace}\\")
                    : '',
            )
            ->render();
    }

    /**
     * Renders the status pill (`Queued` / `Done` / `Failed`) for the captured event type.
     */
    public static function renderStatusCell(JobRecord $record): string
    {
        $variant = JobRecord::EVENT_VARIANTS[$record->eventType]['variant'] ?? 'queued';
        $label = JobRecord::EVENT_VARIANTS[$record->eventType]['label'] ?? 'Queued';

        return Span::tag()
            ->class("yii-debug-queue-status yii-debug-queue-status-{$variant}")
            ->content($label)
            ->render();
    }

    /**
     * Renders the capture time as `HH:MM:SS.mmm`, derived from the captured `microtime` float.
     */
    public static function renderTimeCell(JobRecord $record): string
    {
        $seconds = (int) $record->time;
        $milliseconds = abs((int) (($record->time - $seconds) * 1000));

        return date('H:i:s.', $seconds) . sprintf('%03d', $milliseconds);
    }

    /**
     * Renders the time-to-reserve cell as `Ns` for non-zero TTRs, falling back to an em dash (`—`) otherwise.
     */
    public static function renderTtrCell(JobRecord $record): string
    {
        if ($record->ttr === null || $record->ttr <= 0) {
            return '—';
        }

        return "{$record->ttr}s";
    }
}
